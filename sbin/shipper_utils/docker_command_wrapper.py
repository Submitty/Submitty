"""Pull/remove docker image."""
import argparse
import docker
import sys
import traceback


def parse_arguments():
    """Parse the arguments provided to the script."""
    parser = argparse.ArgumentParser(description='A wrapper for the various\
                                                 systemctl functions. This\
                                                 script must be run as the\
                                                 submitty supervisor.')

    action_group = parser.add_mutually_exclusive_group(required=True)
    action_group.add_argument("--pull", metavar="IMAGE", type=str, help="The image to pull.")
    action_group.add_argument("--remove", action="store_true", help="Remove all unused images.")

    parser.add_argument("--required-images", type=str, nargs='*', help="A list of required images to keep during removal.")
    parser.add_argument("--system-images", type=str, nargs='*', help="A list of system images to always keep during removal.")
    return parser.parse_args()


def remove_images(client, required_images, system_images):
    """Remove all Docker images not in the required or system images lists."""
    print("Beginning removal of Docker images...")

    images_to_keep = set(required_images).union(set(system_images))
    try:
        image_id_to_tags = {}
        tag_to_image_id = {}
        for image in client.images.list():
            tags = image.attrs.get("RepoTags") or []
            for tag in tags:
                tag_to_image_id[tag] = image.id
            image_id_to_tags.setdefault(image.id, []).extend(tags)

        image_tags = set(tag_to_image_id.keys())
        images_to_remove = set.difference(image_tags, images_to_keep)
        for image in images_to_remove:
            print(f"Removed image {image}")

        for image_tag_to_remove in images_to_remove:
            try:
                image_id = tag_to_image_id.get(image_tag_to_remove)
                ref_tags = image_id_to_tags.get(image_id, [])

                # If the image has multiple tags (aliases), remove by tag; otherwise, remove by ID
                if len(ref_tags) > 1:
                    client.images.remove(image_tag_to_remove)
                else:
                    client.images.remove(image_id)

            except docker.errors.ImageNotFound:
                # This can happen if removing by ID clears out multiple tags we were planning to remove.
                print(f"Image/tag {image_tag_to_remove} was already removed, skipping.")
                continue
            except Exception as e:
                print(f"ERROR: An error occurred while removing {image_tag_to_remove}: {e}")
                traceback.print_exc(file=sys.stderr)

    except Exception as e:
        print(f"ERROR: A major error occurred during the removal process: {e}")
        traceback.print_exc(file=sys.stderr)
        # Propagate the exception to cause a non-zero exit code
        raise e

    print("Image removal complete.")


if __name__ == '__main__':
    args = parse_arguments()
    client = docker.client.from_env()

    try:
        if args.remove:
            remove_images(client, args.required_images or [], args.system_images or [])
        elif args.pull:
            repo, tag = args.pull.split(':')
            client.images.pull(repository=repo, tag=tag)
    except Exception:
        traceback.print_exc()
        sys.exit(1)
    sys.exit(0)
