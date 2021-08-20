import docker
import sys

if __name__ == '__main__':
    try:
        docker_client = docker.from_env()
        docker_info = docker_client.info()
        docker_images_obj = docker_client.images.list()
        
        #print the details of the image
        for image in docker_images_obj:
            # rip relevant information
            data = image.attrs
            print(f"\tid: {image.id}")
            print(f"\ttag: ", end = "")
            print(', '.join(data["RepoTags"]))
            print(f'\tcreated: {data["Created"]}')
            print(f'\tsize: {data["Size"]}')
    except Exception as e:
        sys.exit(1)
    sys.exit(0)