#!/usr/bin/env python3

import docker 

def main():
    client = docker.from_env(timeout=60)
    images = client.images.list()
    with open('/var/local/submitty/docker.txt', 'w') as outfile:
        # Print information about each image
        outfile.write("docker Info")
        for image in images:
            outfile.write(f"Image ID: {image.id}\n")
            outfile.write(f"Repo Tags: {', '.join(image.tags)}\n")
            outfile.write(f"Created: {image.attrs['Created']}\n")
            outfile.write(f"Size: {image.attrs['Size']}\n")
            outfile.write("-----\n")

if __name__ == "__main__":
    main()