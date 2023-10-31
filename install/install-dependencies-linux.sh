#!/bin/bash

# Check if 'poppler-utils' is installed
if ! dpkg -l | grep -q '^ii  poppler-utils'; then
    sudo apt-get update
    sudo apt-get install -y poppler-utils
else
    echo "Poppler-utils is already installed."
fi

# Check if 'tesseract' is installed
if ! dpkg -l | grep -q '^ii  tesseract-ocr'; then
    sudo apt-get update
    sudo apt-get install -y tesseract-ocr
else
    echo "Tesseract is already installed."
fi

# Check if 'imagemagick' is installed
if ! dpkg -l | grep -q '^ii  imagemagick'; then
    sudo apt-get update
    sudo apt-get install -y imagemagick
else
    echo "ImageMagick is already installed."
fi

# Find tesseract-path with command: 'tesseract --list-langs'
tessdata_path=$(tesseract --list-langs 2>&1 | grep -o '/.*tessdata')

# Check if path was found
if [ -z "$tessdata_path" ]; then
    echo "Tessdata path couldn't be found. Check if tesseract is installed properly."
    exit 1
fi

# Get traineddata from GitHub
curl -L -o deu.traineddata https://github.com/tesseract-ocr/tessdata_best/raw/main/deu.traineddata
curl -L -o eng.traineddata https://github.com/tesseract-ocr/tessdata_best/raw/main/eng.traineddata
curl -L -o osd.traineddata https://github.com/tesseract-ocr/tessdata_best/raw/main/osd.traineddata

# Move downloaded data to tessdata-folder
sudo mv deu.traineddata $tessdata_path/
sudo mv eng.traineddata $tessdata_path/
sudo mv osd.traineddata $tessdata_path/

echo "Tessdata-files moved to: $tessdata_path."
