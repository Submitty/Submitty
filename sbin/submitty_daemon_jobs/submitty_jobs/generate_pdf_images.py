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
        filename_prefix = os.path.basename(pdf_file_path)[:-4]
        is_matching_file = filename.startswith(filename_prefix) and filename.endswith(".jpg")
        if is_matching_file:
            os.remove(os.path.join(output_dir, filename))
    try:
        pdfPages = PdfReader(pdf_file_path, strict=False)
        with open(pdf_file_path, "rb") as open_file:
            imagePages = convert_from_bytes(
                open_file.read(),
            )
        # Loop through each page in the PDF and save it as an image
        for page_number in range(len(pdfPages.pages)):
            image_filename = os.path.join(
                output_dir,
                "."
                + os.path.basename(pdf_file_path[:-4])
                + "_page_"
                + str(page_number + 1).zfill(2)
                + ".jpg",
            )
            imagePages[page_number].save(
                image_filename, "JPEG", quality=20, optimize=True
            )
            img = Image.open(image_filename)
            draw = ImageDraw.Draw(img)
            for redaction in redactions:
                # Add 1 to page_number because redactions are 1-indexed
                # and page_number is 0-indexed
                if redaction.page_number != page_number + 1:
                    continue
                square_size = 25

                # Convert coordinates from relative to absolute pixel values
                x0 = int(redaction.coordinates[0] * img.size[0])
                y0 = int(redaction.coordinates[1] * img.size[1])
                x1 = int(redaction.coordinates[2] * img.size[0])
                y1 = int(redaction.coordinates[3] * img.size[1])

                # Create a grid of squares within the redaction area
                for y in range(y0, y1, square_size):
                    for x in range(x0, x1, square_size):
                        fill_color = "black" if ((x // square_size + y // square_size) % 2 == 0) else "grey"
                        draw.rectangle(
                            [x, y, x + square_size, y + square_size], fill=fill_color
                        )
            print(f"Saving image {image_filename}")
            img.save(image_filename, "JPEG", quality=20, optimize=True)
    except Exception:
        msg = "Failed when splitting pdf " + pdf_file_path
        print(msg)
        traceback.print_exc()
        # print everything in the buffer just in case it didn't write
        pass
    pass
