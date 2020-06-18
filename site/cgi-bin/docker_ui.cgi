#!/usr/bin/env python3

import cgi
import json
import docker


print("Content-type: text/html")
print()


def collectDockerInfo():
    docker_info = {}
    docker_images = {}

    try:
        docker_client = docker.from_env()
        docker_info = docker_client.info()
        docker_images_obj = docker_client.images.list()
    except Exception as e:
        return json.dumps({"success": False, "error": str(e)})

    docker_images = []
    for image in docker_images_obj:
        # rip relevant information
        data = image.attrs
        docker_images.append({
            "id": image.id,
            "labels": image.labels,
            "short_id": image.short_id,
            "tags": data["RepoTags"],
            "created": data["Created"],
            "size": data["Size"],
            "virtual_size": data["VirtualSize"]
        })

    return json.dumps({"success": True,
                       "docker_images": docker_images,
                       "docker_info": docker_info})


if __name__ == "__main__":
    print(collectDockerInfo())
