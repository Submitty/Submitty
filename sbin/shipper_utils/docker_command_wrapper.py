"""Pull in a docker image."""
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
    parser.add_argument("image", metavar="IMAGE", type=str, help="The image to pull")
    return parser.parse_args()


if __name__ == '__main__':
    args = parse_arguments()

    client = docker.client.from_env()

    try:
        repo, tag = args.image.split(':')
        client.images.pull(repository=repo, tag=tag)
        docker_info = client.info()
        docker_images_obj = client.images.list()
        #print the details of the image
        for i in docker_images_obj:
            # rip relevant information
            data = i.attrs
            print(f"{args.image}-id: {i.id}")
            print(f"{args.image}-tag: ", end = "")
            print(', '.join(data["RepoTags"]))
            print(f'{args.image}-created: {data["Created"]}')
    except Exception:
        traceback.print_exc()
        sys.exit(1)
    sys.exit(99)
