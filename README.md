# Pdf Interpreter

## Introduction

This class is designed to convert multiple PDF files, whether image-based or text-based, into an array of data.
The class uses user-defined templates containing regular expressions to control the data extraction process, allowing for customized and flexible output.

## Table of Contents

This README is divided into several sections:
* [Installation](#installation)
  * [Homebrew packages](#homebrew-packages)
  * [Automated installation](#automated-installation)
  * [Manual installation](#manual-installation)
  * [Tesseract Language Files ](#tesseract-language-files)
* [Usage](#usage)
  * [Create Object](#create-object)
  * [Get Sample Output](#get-sample-output)
  * [Set new Template](#set-new-template)
  * [Add pattern to template](#add-pattern-to-template)
  * [Get template](#get-template)
  * [Delete template](#delete-template)
  * [Convert Files from Folder](#convert-files-from-folder)
  * [Convert File](#convert-file)

## Installation
Add the following code to your `composer.json`:
```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/joest8/pdfinterpreter"
        }
    ],
    "require": {
        "joest8/pdfinterpreter": "dev-main"
    }
}
```

### Homebrew packages
To use this class, you'll need to install the following Homebrew packages:

1. **Poppler** (necessary to convert pdf to text and get information about number of pages in file)
2. **Tesseract** (necessary to read and interpret png file)
3. **ImageMagick** (necessary to convert pdf->png)

Make sure `homebrew` is installed. 

### Automated installation
Run the following code from the source folder to autoinstall all dependencies and tesseract language files:
```sh
php install/install_dependencies.php
```

### Manual installation of homebrew packages
If homebrew is installed run the following commands to install the Homebrew packages:
```sh
brew install poppler tesseract imagemagick
```

### Manual installation of Tesseract Language Files
You also need to install the required Tesseract language files. You can check the available languages at:
https://github.com/tesseract-ocr/tessdata_best/

Download the necessary language files and place them in the appropriate directory.
To find the directory use:
```sh
tesseract --list-langs
```

## Usage

### Create Object
```php
<?php
require_once '../vendor/autoload.php';

use PdfInterpreter;

//get path from terminal: 'echo $PATH'
$path_env = "/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin:/opt/homebrew/bin:/opt/homebrew/bin";
$pdf = new PdfInterpreter($path_env);
```

### Get Sample Output

Using the `get_sample_output`-Method will allow you to get a sample of a text output without any interpretation of patterns.
```php
<?php
require_once '../vendor/autoload.php';

use PdfInterpreter;

//get path from terminal: 'echo $PATH'
$path_env = "/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin:/opt/homebrew/bin:/opt/homebrew/bin";
$pdf = new PdfInterpreter($path_env);

print_r($pdf->get_sample_output());
```

### Set new template

Using the `add_new_template`-Method will help you to create a new template.
For more informations about the demanded parameters read the DocBloc of the method.

```php
<?php
require_once '../vendor/autoload.php';

use PdfInterpreter;

//get path from terminal: 'echo $PATH'
$path_env = "/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin:/opt/homebrew/bin:/opt/homebrew/bin";
$pdf = new PdfInterpreter($path_env);

$pdf->add_new_template("sample","Sample","/[Cc]ompany[\W]?[Aa][Bb][Cc]/","1","eng");
```

### Add pattern to template

Using the `add_pattern_to_template`-Method will help you to add a new pattern to an existing template.
For more informations about the demanded parameters read the DocBloc of the method.

```php
$pdf->add_pattern_to_template("sample","invoice_no","/INVOICE # *([\d]*)/","1");
$pdf->add_pattern_to_template("sample","date","/INVOICE DATE *([\d]{2}.[\d]{2}.[\d]{4})/","1");
$pdf->add_pattern_to_template("sample","positions","/([\d]{1,4}) *(.*?) *([\d]{1,8},[\d]{2}) *([\d]{1,8},[\d]{2})/m","a",true,['pieces','item','price','amount']);
```

### Get Template

Using the `get_template`-Method will return the entire template.
For more informations about the demanded parameters read the DocBloc of the method.
```php
print_r($pdf->get_template("sample"));
```

### Delete Template

Using the `delete_template`-Method will delete the entire template.
For more informations about the demanded parameters read the DocBloc of the method.
```php
print_r($pdf->delete_template("sample"));
```

### Convert Files from Folder

Using the `convert_folder`-Method will convert all files from a folder into an array of data.
For more informations about the demanded parameters read the DocBloc of the method.
```php
print_r(print_r($pdf->convert_folder("/../docs/",true,false,ocr_lang: "eng")));
```

### Convert File

Using the `convert_file`-Method will convert a single file into an array of data.
For more informations about the demanded parameters read the DocBloc of the method.
```php
print_r($pdf->convert_file("/../docs/sample-bill.pdf",true,false));
```

