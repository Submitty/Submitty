import os
import traceback
from typing import List, Sequence

from pdf2image import convert_from_bytes
from PIL import Image, ImageDraw
from PyPDF2 import PdfReader


class Redaction:
    def __init__(self, page_number: int, coordinates: Sequence[float]):
        self.page_number = page_number
        self.coordinates = coordinates


def main(pdf_file_path: str, output_dir: str, redactions: List[Redaction]):
    directory = os.path.dirname(pdf_file_path)
    if directory:
        os.chdir(os.path.dirname(pdf_file_path))
    # Ensure the output directory exists
    if not os.path.exists(output_dir):
        os.makedirs(output_dir)
    # Delete any existing images that match the pattern
    for filename in os.listdir(output_dir):
        if filename.startswith(os.path.basename(pdf_file_path)[:-4]) and filename.endswith(".jpg"):
            os.remove(os.path.join(output_dir, filename))
    try:
        pdfPages = PdfReader(pdf_file_path, strict=False)
        with open(pdf_file_path, "rb") as open_file:
            imagePages = convert_from_bytes(
                open_file.read(),
            )
        for page_number in range(len(pdfPages.pages)):
            image_filename = (
                output_dir
                + "/."
                + os.path.basename(pdf_file_path)[:-4]
                + "_page_"
                + str(page_number + 1).zfill(2)
                + ".jpg"
            )
            imagePages[page_number].save(
                image_filename, "JPEG", quality=20, optimize=True
            )
            for redaction in redactions:
                if redaction.page_number != page_number + 1:
                    continue
                img = Image.open(image_filename)
                draw = ImageDraw.Draw(img)
                draw.rectangle(
                    [
                        redaction.coordinates[0] * img.size[0],
                        redaction.coordinates[1] * img.size[1],
                        redaction.coordinates[2] * img.size[0],
                        redaction.coordinates[3] * img.size[1],
                    ],
                    fill="black",
                )
                img.save(image_filename, "JPEG", quality=20, optimize=True)
    except Exception:
        msg = "Failed when splitting pdf " + pdf_file_path
        print(msg)
        traceback.print_exc()
        # print everything in the buffer just in case it didn't write
        pass
    pass
