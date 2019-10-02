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
            retval, thresh_gray = cv2.threshold(new_img, thresh=100, maxval=255, \
                                   type=cv2.THRESH_BINARY_INV)

            contours, h = cv2.findContours(thresh_gray, cv2.RETR_TREE, cv2.CHAIN_APPROX_NONE)
            if len(contours) < 1:
                continue

            c = max(contours, key = cv2.contourArea)
            x,y,w,h = cv2.boundingRect(c)

            cut = new_img[y:y+h,x:x+w]
            y,x = cut.shape

            left = right = top = bottom = 0

            # increase x
            if y > x: 
                delta_x = abs(y - x)
                left = right = x // 2

                top = bottom = 0
            # increase y
            elif x > y:
                delta_y = abs(x - y)
                top = bottom = y // 2

                left = right = 0
            else:
                left = right = top = bottom = 0

            border_size = 10

            left += border_size
            right += border_size
            top += border_size
            bottom += border_size

            new_img = cv2.copyMakeBorder(cut, top, bottom, left, right, cv2.BORDER_CONSTANT,
                value=[255,255,255])

            new_img = cv2.resize(new_img, (28, 28), interpolation=cv2.INTER_AREA)
            cv2.imwrite("/usr/local/submitty/GIT_CHECKOUT/Submitty/foo" + str(idx) + ".png", new_img)

            # sharpen img and invert it
            kernel = np.zeros( (9,9), np.float32)
            kernel[4,4] = 2.0
            boxFilter = np.ones( (9,9), np.float32) / 81.0
            kernel = kernel - boxFilter

            new_img = 255 - new_img
            new_img = cv2.filter2D(new_img, -1, kernel)

            cv2.imwrite("/usr/local/submitty/GIT_CHECKOUT/Submitty/edit" + str(idx) + ".png", new_img)


            new_img = new_img / (0.5 * np.max(new_img)) - 1

            # convert image to expected tensor (vector)
            new_img = np.expand_dims(new_img, axis=0)
            new_img = np.expand_dims(new_img, axis=0)
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
        max_ = 0
        index_ = 0
        max_index = 0

        for i in out:
            if i > max_:
                max_ = i
                max_index = index_

            index_ += 1

        # and the digit....*drumroll*....is
        ret += str(max_index) 

    return ret


def getDigits(page, qr_data):
    """Driver function for performing OCR to find student numbers."""

    cv2.imwrite("/usr/local/submitty/GIT_CHECKOUT/Submitty/original.png", page)

    # convert to grayscale
    page = cv2.cvtColor(page, cv2.COLOR_BGR2GRAY)

    # bounding box for QR code
    left  = qr_data[0][2][0]
    top   = qr_data[0][2][1]
    width = qr_data[0][2][2]
    height = qr_data[0][2][3]

    # real position of 4 edges of QR code
    rect = np.array(qr_data[0][3])

    # deskew the img first
    rect = cv2.minAreaRect(rect)
    angle = rect[-1]

    if angle < -45:
        angle = -(90 + angle)
    
    page_height, page_width = page.shape

    center = ( page_width // 2, page_height // 2)

    if abs(angle) > 0:
        M = cv2.getRotationMatrix2D(center, angle, 1.0)
        page = cv2.warpAffine(page, M, (page_width, page_height), flags=cv2.INTER_CUBIC, borderMode=cv2.BORDER_REPLICATE)

    # narrow down the search space by looking only to the right of the QR
    sub_size = (left + width, top, page_width, top + height)
    page = page[sub_size[1]:sub_size[3], sub_size[0]:sub_size[2]]

    processed_images = preprocess(page)
    return scanForDigits(processed_images)
