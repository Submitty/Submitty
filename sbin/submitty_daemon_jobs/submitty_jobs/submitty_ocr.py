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
    import onnxruntime as rt
except ImportError:
    traceback.print_exc()
    raise ImportError("One or more required python modules not installed correctly")


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
    # TODO : we could investigate using bluring here to remove noise to improve results

    # Threshold image
    # SRC: https://medium.com/coinmonks/a-box-detection-algorithm-for-any-image-containing-boxes-756c15d7ed26 # noqa: E501
    img = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
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
        # TODO Find better way to determine what cotnours are the boxes and whats not
        if(w > 20 and w < 50):
            idx += 1
            new_img = img[y:y+h, x:x+w]
            # convert to MNIST expected img
            # resize to 28x28,invert, and leave only 1 channel
            new_img = cv2.resize(new_img, (28, 28), interpolation=cv2.INTER_AREA)
            new_img = 255 - new_img
            new_img = new_img / np.max(new_img)

            # convert image to expected tensor (vector)
            new_img = np.expand_dims(new_img, axis=0)
            new_img = [new_img]
            new_img = np.array(new_img)
            new_img = new_img.astype(np.float32)

            sub_boxes.append(new_img)

    return sub_boxes


def scanForDigits(images):
    """Take the processed sub-images and perform OCR."""
    model_path = os.path.join(os.path.dirname(os.path.realpath(__file__)),
                              'MNIST', 'model.onnx')
    sess = rt.InferenceSession(model_path)
    input_name = sess.get_inputs()[0].name
    output_name = sess.get_outputs()[0].name

    ret = ""

    for image in images:
        out = sess.run([output_name], {input_name: image})
        out = out[0][0]
        out = out.astype(np.float128)

        # run through softmax to map the outputs to probability distrabution
        e_x = np.exp(out - np.max(out))
        out = e_x / e_x.sum(axis=0)

        # get the index of whatever the highest confidence is
        max_val = max_index = 0
        for i in range(len(out)):
            if out[i] > max_val:
                max_val = out[i]
                max_index = i

        # and the digit....*drumroll*....is
        ret += str(max_index) if out[max_index] >= 0.94 else '*'

    return ret


def getDigits(page, qr_position):
    """Driver function for performing OCR to find student numbers."""
    left = qr_position[0]
    top = qr_position[1]
    width = qr_position[2]
    height = qr_position[3]

    page_height, page_width, channels = page.shape

    # narrow down the search space by looking only to the right of the QR
    sub_size = (left + width, top, page_width, top + height)
    page = page[sub_size[1]:sub_size[3], sub_size[0]:sub_size[2]]

    processed_images = preprocess(page)
    return scanForDigits(processed_images)
