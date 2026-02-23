import os
import traceback
import hashlib
import io
from typing import List, Sequence, Dict
from concurrent.futures import ThreadPoolExecutor
import time

import fitz  # PyMuPDF
from PIL import ImageDraw, Image


class Redaction:
    def __init__(self, page_number: int, coordinates: Sequence[float]):
        self.page_number = page_number
        self.coordinates = coordinates


def create_redaction_pattern(width: int, height: int, square_size: int = 25) -> Image.Image:
    """Create a reusable checkered pattern for redactions."""
    pattern = Image.new('RGB', (width, height), 'white')
    draw = ImageDraw.Draw(pattern)
    
    for y in range(0, height, square_size):
        for x in range(0, width, square_size):
            fill_color = "black" if ((x // square_size + y // square_size) % 2 == 0) else "grey"
            draw.rectangle(
                [x, y, min(x + square_size, width), min(y + square_size, height)], 
                fill=fill_color
            )
    return pattern


def apply_redaction_optimized(img: Image.Image, redaction: 'Redaction', pattern_cache: Dict[tuple, Image.Image]) -> None:
    """Apply redaction using pre-computed pattern for better performance."""
    # Convert coordinates from relative to absolute pixel values
    x0 = int(redaction.coordinates[0] * img.size[0])
    y0 = int(redaction.coordinates[1] * img.size[1])
    x1 = int(redaction.coordinates[2] * img.size[0])
    y1 = int(redaction.coordinates[3] * img.size[1])
    
    width = x1 - x0
    height = y1 - y0
    
    # Use cached pattern or create new one
    pattern_key = (width, height)
    if pattern_key not in pattern_cache:
        pattern_cache[pattern_key] = create_redaction_pattern(width, height)
    
    # Paste the pattern onto the image
    img.paste(pattern_cache[pattern_key], (x0, y0))


def get_file_hash(pdf_file_path: str) -> str:
    """Get hash of PDF file for caching purposes."""
    with open(pdf_file_path, 'rb') as f:
        return hashlib.md5(f.read()).hexdigest()


def main(pdf_file_path: str, output_dir: str, redactions: List[Redaction]):
    start_time = time.time()
    
    # Early exit if no redactions
    if not redactions:
        print(f"No redactions provided, skipping processing for {pdf_file_path}")
        return
    
    directory = os.path.dirname(pdf_file_path)
    if directory:
        os.chdir(os.path.dirname(pdf_file_path))
    
    # Ensure the output directory exists
    if not os.path.exists(output_dir):
        os.makedirs(output_dir)
    
    # Group redactions by page for efficient processing
    redactions_by_page = {}
    for redaction in redactions:
        if redaction.page_number not in redactions_by_page:
            redactions_by_page[redaction.page_number] = []
        redactions_by_page[redaction.page_number].append(redaction)
    
    # Cache for redaction patterns
    pattern_cache = {}
    
    try:
        # Check if we need to regenerate (optional optimization)
        cache_file = os.path.join(output_dir, ".cache_info")
        current_hash = get_file_hash(pdf_file_path)
        
        # Only proceed if cache doesn't exist or hash changed
        if os.path.exists(cache_file):
            with open(cache_file, 'r') as f:
                cached_hash = f.read().strip()
            if cached_hash == current_hash:
                print(f"Skipping {pdf_file_path} - unchanged (cached)")
                return
        
        # Open PDF with PyMuPDF
        pdf_document = fitz.open(pdf_file_path)
        
        # Process only pages that have redactions
        pages_to_process = set(redactions_by_page.keys())
        
        # Process each page that has redactions
        for page_number in range(len(pdf_document)):
            # Skip pages without redactions
            if (page_number + 1) not in redactions_by_page:
                continue
                
            image_filename = os.path.join(
                output_dir,
                "."
                + os.path.basename(pdf_file_path[:-4])
                + "_page_"
                + str(page_number + 1).zfill(2)
                + ".jpg",
            )
            
            # Get page and render to image
            page = pdf_document[page_number]
            # Render at higher DPI for better quality
            mat = fitz.Matrix(2.0, 2.0)  # 2x zoom
            pix = page.get_pixmap(matrix=mat)
            
            # Convert to PIL Image
            img_data = pix.tobytes("ppm")
            img = Image.open(io.BytesIO(img_data))
            
            # Apply all redactions for this page
            for redaction in redactions_by_page[page_number + 1]:
                apply_redaction_optimized(img, redaction, pattern_cache)
            
            print(f"Saving image {image_filename}")
            img.save(image_filename, "JPEG", quality=20, optimize=True)
        
        # Close PDF
        pdf_document.close()
        
        # Update cache
        with open(cache_file, 'w') as f:
            f.write(current_hash)
            
        elapsed = time.time() - start_time
        print(f"Processed {pdf_file_path} in {elapsed:.2f} seconds")
        
    except Exception:
        msg = "Failed when splitting pdf " + pdf_file_path
        print(msg)
        traceback.print_exc()
        pass
