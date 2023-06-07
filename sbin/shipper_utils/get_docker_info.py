import docker


def printDockerInfo(logs = None):
    try:
        docker_client = docker.from_env()
        docker_info = docker_client.info()
        docker_images_obj = docker_client.images.list()

        # print the details of the image
        if (logs == None){
            print(f"Docker Version: {docker_info['ServerVersion']}")
        }
        else{
            logs.append(f"Docker Version: {docker_info['ServerVersion']}")
        }
        for image in docker_images_obj:
            # rip relevant information
            data = image.attrs

            if (logs == None){
                print("Tag: ", end="")
                print(', '.join(data["RepoTags"]))
                print(f"\t-id: {image.short_id}")
                print(f'\t-created: {data["Created"]}')
                print(f'\t-size: {data["Size"]}')
            }
            else{
                logs.append("Tag: ", end="")
                logs.append(', '.join(data["RepoTags"]))
                logs.append(f"\t-id: {image.short_id}")
                logs.append(f'\t-created: {data["Created"]}')
                logs.append(f'\t-size: {data["Size"]}')
            }
        return True
    except docker.errors.APIError:
        if (logs == None){
            print("APIError was raised.")
        }
        else{
            logs.log("APIError was raised.")
        }
    return False


if __name__ == '__main__':
    printDockerInfo()
