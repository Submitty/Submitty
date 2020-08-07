#!/usr/bin/env python3

"""Given openCV image array, search for handwritten digits and attempt to read them."""

# Submitty_ocr.py uses the open neural network exchange (ONNX) runtime module to perform
# Optical charactor recognition on images containing handwritten digits.
# A model pretrained on the MNIST database is used and is provided by Microsoft's
# Cogntive toolkit (CNTK) under the MIT license and can be found here :
# https://gallery.azure.ai/Model/MNIST-Handwritten-Digit-Recognition
import traceback
import numpy as np
import cv2
import os

try:
    import pyzbar.pyzbar as pyzbar
    from pyzbar.pyzbar import ZBarSymbol
    import onnxruntime as rt
except ImportError:
    traceback.print_exc()
    raise ImportError("One or more required python modules not installed correctly")

expected_box_count = 9


def sort_contours(cnts):
    """Order contours left to right so we don't loose ordering."""
    reverse = False
    i = 0
    # construct the list of bounding boxes and sort them
    boundingBoxes = [cv2.boundingRect(c) for c in cnts]
    cnts, boundingBoxes = zip(*sorted(zip(cnts, boundingBoxes),
                              key=lambda b: b[1][i], reverse=reverse))

    # return the list of sorted contours and bounding boxes
    return (cnts, boundingBoxes)


def preprocess(img):
    """Find the box containing the digits and convert them to MNIST format."""
    # Threshold image
    # SRC: https://medium.com/coinmonks/a-box-detection-algorithm-for-any-image-containing-boxes-756c15d7ed26 # noqa: E501
    thresh, img_bin = cv2.threshold(img, 128, 255, cv2.THRESH_BINARY | cv2.THRESH_OTSU)
    img_bin = 255 - img_bin

    # Defining a kernel length
    kernel_length = np.array(img).shape[1]//80

    verticle_kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (1, kernel_length))
    horizont_kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (kernel_length, 1))
    kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (3, 3))

    # Search for vertical and horizontal lines
    img_temp1 = cv2.erode(img_bin, verticle_kernel, iterations=3)
    verticle_lines_img = cv2.dilate(img_temp1, verticle_kernel, iterations=3)

    img_temp2 = cv2.erode(img_bin, horizont_kernel, iterations=3)
    horizontal_lines_img = cv2.dilate(img_temp2, horizont_kernel, iterations=3)

    alpha = 0.5
    beta = 1.0 - alpha

    img_final_bin = cv2.addWeighted(verticle_lines_img,
                                    alpha, horizontal_lines_img, beta, 0.0)
    img_final_bin = cv2.erode(~img_final_bin, kernel, iterations=3)

    thresh, img_final_bin = cv2.threshold(img_final_bin, 128, 255,
                                          cv2.THRESH_BINARY | cv2.THRESH_OTSU)

    img_final_bin = 255 - img_final_bin
    contours, hierarchy = cv2.findContours(img_final_bin, cv2.RETR_TREE,
                                           cv2.CHAIN_APPROX_SIMPLE)
    contours, boundingBoxes = sort_contours(contours)

    sub_boxes = []
    idx = 0
    # get the individual images
    for c in contours:
        x, y, w, h = cv2.boundingRect(c)
        if(w > 20 and w < 75 and h > 35 and h < 125):
            idx += 1
            new_img = img[y:y+h, x:x+w]
            # convert to MNIST expected img
            # resize to 28x28,invert, and leave only 1 channel
            retval, thresh_gray = cv2.threshold(new_img, thresh=100, maxval=255,
                                                type=cv2.THRESH_BINARY_INV)

            # clean up noise and fill in any holes in the digit
            thresh_gray = cv2.medianBlur(thresh_gray, 3)
            kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (2, 2))
            thresh_gray = cv2.dilate(thresh_gray, kernel, iterations=1)
            thresh_gray = cv2.morphologyEx(thresh_gray, cv2.MORPH_CLOSE, kernel)

            contours, h = cv2.findContours(thresh_gray, cv2.RETR_TREE,
                                           cv2.CHAIN_APPROX_NONE)
            if len(contours) < 1:
                continue

            contours = list(reversed(sorted(contours,
                                            key=lambda x: cv2.contourArea(x))))

            # all noise has been removed, so we can combine the left over components
            c = contours[0]
            for c_index in range(1, len(contours)):
                c = np.vstack((c, contours[c_index]))

            x, y, w, h = cv2.boundingRect(c)

            thresh_gray = 255 - thresh_gray
            cut = thresh_gray[y:y+h, x:x+w]

            # redraw the digit at the center of the image
            cut_height = (y + h) - y
            cut_width = (x + w) - w

            if cut_height > cut_width:
                left = right = x // 2

                top = bottom = 0
            elif cut_width > cut_width:
                top = bottom = y // 2

                left = right = 0
            else:
                left = right = top = bottom = 0

            border_size = 5

            left += border_size
            right += border_size
            top += border_size
            bottom += border_size

            new_img = cv2.copyMakeBorder(cut, top, bottom, left, right,
                                         cv2.BORDER_CONSTANT,
                                         value=[255, 255, 255])
            new_img = cv2.dilate(new_img, kernel)

            # downscale to expected size with border
            sf = 2
            new_img = cv2.resize(new_img, (28, 28), fx=sf, fy=sf,
                                 interpolation=cv2.INTER_AREA)

            # force a border increase incase the resizing caused a digit to hit the edge
            border_size = 2
            new_img = cv2.copyMakeBorder(new_img, border_size, border_size,
                                         border_size, border_size,
                                         cv2.BORDER_CONSTANT,
                                         value=[255, 255, 255])

            new_img = cv2.resize(new_img, (28, 28), fx=sf, fy=sf,
                                 interpolation=cv2.INTER_AREA)

            kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (2, 2))
            new_img = cv2.dilate(new_img, kernel, iterations=1)
            new_img = 255 - new_img

            # normalize from 0-255 to 0-1
            new_img = new_img / 255
            # convert image to expected tensor (vector)
            new_img = np.expand_dims(new_img, axis=0)
            new_img = np.expand_dims(new_img, axis=0)
            new_img = new_img.astype(np.float32)

            sub_boxes.append(new_img)

    return sub_boxes


def scanForDigits(images):
    """Take the processed sub-images and perform OCR."""
    # Returns a string of the decoded digits
    model_path = os.path.join(os.path.dirname(os.path.realpath(__file__)),
                              'onnx_models', 'mnist.onnx')
    sess = rt.InferenceSession(model_path)
    input_name = sess.get_inputs()[0].name
    output_name = sess.get_outputs()[0].name

    ret = ""
    confidences = []
    for image in images:
        out = sess.run([output_name], {input_name: image})
        out = out[0][0]
        out = out.astype(np.float64)

        # run through softmax to map the outputs to probability distrabution
        # https://docs.scipy.org/doc/scipy/reference/generated/scipy.special.softmax.html
        out = np.exp(out) / sum(np.exp(out))
        max_index = np.argmax(out)

        confidences.append(out[max_index])
        ret += str(max_index)
        if len(ret) == expected_box_count:
            break

    return ret, confidences


def getDigits(page, qr_data):
    """Driver function for performing OCR to find student numbers."""
    # real position of 4 edges of QR code
    rect = np.array(qr_data[0][3])
    rect = cv2.minAreaRect(rect)
    angle = rect[-1]

    if (angle < -45):
        angle += 90

    page_height, page_width = page.shape
    center = (page_width // 2, page_height // 2)

    # rotate page if not straight relative to QR code
    M = cv2.getRotationMatrix2D(center, angle, 1.0)
    page = cv2.warpAffine(page, M, (page_width, page_height),
                          flags=cv2.INTER_CUBIC, borderMode=cv2.BORDER_REPLICATE)

    # QR code has shifted, rescan
    qr_data = pyzbar.decode(page, symbols=[ZBarSymbol.QRCODE])

    # bounding box for QR code
    left = qr_data[0][2][0]
    top = qr_data[0][2][1]
    width = qr_data[0][2][2]
    height = qr_data[0][2][3]

    # narrow down the search space by looking only to the right of the QR
    sub_size = (left + width, top, page_width, top + height)
    page = page[sub_size[1]:sub_size[3], sub_size[0]:sub_size[2]]

    processed_images = preprocess(page)
    return scanForDigits(processed_images)
