# Open Neural Network Exchange Models (ONNX)
- https://onnx.ai/
- https://github.com/onnx


This folder contains pretrained ONNX models that
can be loaded and used within a Submitty job.

## MNIST

The MNIST model is used for performing Optical Character Recognition (OCR)
on handwritten digits.
Submitty_ocr.py uses the model to recognize numeric IDs on scanned exams. 

The model has been trained on the MNIST database and has been developed and 
released by Microsoft's Cogntive toolkit (CNTK) under the MIT license and can be found here : https://gallery.azure.ai/Model/MNIST-Handwritten-Digit-Recognition 

Additional details about the model can be found here : https://github.com/Microsoft/CNTK/blob/master/Tutorials/CNTK_103D_MNIST_ConvolutionalNeuralNetwork.ipynb
