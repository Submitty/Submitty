import docker
import sys

def printDockerInfo():
    try:
        docker_client = docker.from_env()
        docker_info = docker_client.info()
        docker_images_obj = docker_client.images.list()

        #print the details of the image
        print(f"Docker Version: {docker_info['ServerVersion']}")
        for image in docker_images_obj:
            # rip relevant information
            data = image.attrs
            print(f"Tag: ", end = "")
            print(', '.join(data["RepoTags"]))
            print(f"\t-id: {image.short_id}")
            print(f'\t-created: {data["Created"]}')
            print(f'\t-size: {data["Size"]}')
    except Exception as e:
        return False
    return True

if __name__ == '__main__':
    if (not printDockerInfo()):
        sys.exit(1)
    sys.exit(0)