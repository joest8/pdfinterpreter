#!/bin/bash

# check if 'poppler' is installed
if ! brew list --formula | grep -q '^poppler$'; then
    brew install poppler
else
    echo "Poppler is already installed."
fi

# check if 'tessercat' is installed
if ! brew list --formula | grep -q '^tesseract$'; then
    brew install tesseract
else
    echo "Tessercat is already installed."
fi

# check if 'imagemagick' is installed
if ! brew list --formula | grep -q '^imagemagick$'; then
    brew install imagemagick
else
    echo "ImageMagick is already installed."
fi

# find tesseract-path with command: 'tesseract --list-langs'
tessdata_path=$(tesseract --list-langs 2>&1 | grep -o '/.*tessdata')

# check if path was found
if [ -z "$tessdata_path" ]; then
    echo "Tessdata path couldn't be found. Check if tesseract is installed properly"
    exit 1
fi

# get traineddata from GitHub
curl -L -o deu.traineddata https://github.com/tesseract-ocr/tessdata_best/raw/main/deu.traineddata
curl -L -o eng.traineddata https://github.com/tesseract-ocr/tessdata_best/raw/main/eng.traineddata
curl -L -o osd.traineddata https://github.com/tesseract-ocr/tessdata_best/raw/main/osd.traineddata

# move downloaded data to tessdata-folder
sudo mv deu.traineddata $tessdata_path/
sudo mv eng.traineddata $tessdata_path/
sudo mv osd.traineddata $tessdata_path/

echo "Tessdata-files moved to: $tessdata_path."