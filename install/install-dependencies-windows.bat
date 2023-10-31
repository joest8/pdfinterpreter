# Check if Chocolatey is installed
if ! command -v choco >/dev/null 2>&1; then
    echo "Installing Chocolatey..."
    @powershell -NoProfile -ExecutionPolicy Bypass -Command "iex ((New-Object System.Net.WebClient).DownloadString('https://chocolatey.org/install.ps1'))" && SET "PATH=%PATH%;%ALLUSERSPROFILE%\chocolatey\bin"
else
    echo "Chocolatey is already installed."
fi

# Install Poppler
if ! choco list --localonly | findstr /R "^poppler "; then
    choco install poppler
else
    echo "Poppler is already installed."
fi

# Install Tesseract
if ! choco list --localonly | findstr /R "^tesseract "; then
    choco install tesseract
else
    echo "Tesseract is already installed."
fi

# Install ImageMagick
if ! choco list --localonly | findstr /R "^imagemagick "; then
    choco install imagemagick
else
    echo "ImageMagick is already installed."
fi

# Find tessdata path
for /F "tokens=*" %%i in ('tesseract --list-langs 2^>^&1 ^| findstr /R "^Tessdata"') do SET tessdata_path=%%i
set tessdata_path=%tessdata_path:~10%

# Check if path was found
if "%tessdata_path%"=="" (
    echo "Tessdata path couldn't be found. Check if Tesseract is installed properly"
    exit /b 1
)

# Download traineddata files
curl -L -o deu.traineddata https://github.com/tesseract-ocr/tessdata_best/raw/main/deu.traineddata
curl -L -o eng.traineddata https://github.com/tesseract-ocr/tessdata_best/raw/main/eng.traineddata
curl -L -o osd.traineddata https://github.com/tesseract-ocr/tessdata_best/raw/main/osd.traineddata

# Move downloaded files to tessdata folder
move deu.traineddata "%tessdata_path%"
move eng.traineddata "%tessdata_path%"
move osd.traineddata "%tessdata_path%"

echo "Tessdata files moved to: %tessdata_path%."
