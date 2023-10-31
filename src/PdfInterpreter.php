<?php
declare(strict_types=1);

namespace PdfInterpreter;

use Symfony\Component\Process\Process as Process;

class PdfInterpreter
{
    //------------------------------------------------------------------------------------------------------------------
    // MEMBER VARIABLES
    //------------------------------------------------------------------------------------------------------------------
    //paths
    private string $temp_folder;  //folder with files to convert
    private string $log_folder;  //folder with log-files
    private string $path_env;  //system PATH environment (in terminal: 'echo $PATH')

    private string $syspath_pdfinfo;  //system-path to Popplers pdf-info
    private string $ocr_syspath_tesseract;  //system-path to TesseractOCR
    private string $ocr_syspath_convert;  //system-path to ImageMagick-Software-Suite
    private string $ptt_syspath_pdftotext;  //system-path to pdftotext

    //temp doc data
    private array $doc_template;  //template of document
    private array $doc_ptt;  //pdftotext-result as array with different page-combinations
    private array $doc_ocr;  //ocr-result as array with different page-combinations

    //user settings
    private array $templates;  //array with default templates

    //log
    private string $error_logs = "";  //log-data of errors

    //------------------------------------------------------------------------------------------------------------------
    // PUBLIC METHODS
    //------------------------------------------------------------------------------------------------------------------

    /**
     * ReadInvoices
     *
     * @param string $path_env system PATH environment (get in terminal: 'echo $PATH')
     * @param string $temp_folder (optional) relative path to save temporary files (f.e. image.png of TesseractOCR).
     * @param string $log_folder folder path to save log-file
     *
     */
    public function __construct(string $path_env, string $temp_folder = "/../logs/", string $log_folder="/../tmp/")
    {
        //set global variables
        $this->path_env = $path_env;

        //check file path for temp files
        $file_path = $this->check_file_path($temp_folder, true);
        if (array_keys($file_path)[0] === 'error' && $temp_folder === "/../logs/") {
            mkdir(__DIR__ . '/../logs');
            $this->temp_folder = __DIR__ . '/../logs';
        }
        elseif (array_keys($file_path)[0] === 'error' && $temp_folder !== "/../logs/") {
            echo "Error: " . $file_path['error'];
            exit();
        } else {
            $this->temp_folder = $file_path['success'];
        };

        //check file path for log files
        $file_path = $this->check_file_path($log_folder, true);
        if (array_keys($file_path)[0] === 'error' && $temp_folder === "/../tmp/") {
            mkdir(__DIR__ . '/../tmp');
            $this->temp_folder = __DIR__ . '/../tmp';
        }
        elseif (array_keys($file_path)[0] === 'error' && $temp_folder !== "/../tmp/") {
            echo "Error: " . $file_path['error'];
            exit();
        } else {
            $this->log_folder = $file_path['success'];
        };

        //set templates
        $this->set_templates();
    }

    /**
     * add_new_template
     *
     * @param string $id unique template identifier
     * @param string $title title of template (f.e. merchant of invoice)
     * @param string $regex regex to identify doc-template
     * @param string $page_detection string with page(s) to look-up for identifying the doc-template. Default: ["1"]
     * @param string $page_detection > "a" = all pages
     * @param string $page_detection > "1","2","3" = n-page
     * @param string $page_detection > "l" = last page
     * @param string $language language used in pdf. Format: ISO 639-3. (make sure the related Tesseract language file exist)
     * @param bool $override_templates true if existing template with $id should be overridden
     */
    public function add_new_template(string $id, string $title, string $regex, string $page_detection = "1", string $language = "eng", bool $override_templates = true)
    {
        $template = [
            "title" => $title,
            "regex" => $regex,
            "language" => $language,
            "page_detection" => $page_detection
        ];
        $file = dirname(__FILE__, 2)."/templates/".$id.".json";
        if (!file_exists($file) || $override_templates === true) {
            file_put_contents($file, json_encode($template,JSON_PRETTY_PRINT));
        }

        //set templates
        $this->set_templates();
    }

    /**
     * add_pattern_to_template
     *
     * @param string $template_id id of template
     * @param string $title title of key in return-array
     * @param string $regex regex to find value(s)
     * @param string $page_detection string with page(s) to look-up to find the pattern. Default: ["a"]
     * @param string $page_detection > "a" = all pages
     * @param string $page_detection > "1","2","3" = n-page
     * @param string $page_detection > "l" = last page
     * @param bool $multi_matches true: return array with all matches in document | false: return first match
     * @param array|null $capture_assignment if regex has multiple capture-groups list up the assignments for each capture
     *
     * @return string status of process
     */
    public function add_pattern_to_template(string $template_id, string $title, string $regex, string $page_detection = "a", bool $multi_matches = false, array $capture_assignment = null)
    {

        $file = dirname(__FILE__, 2)."/templates/".$template_id.".json";
        if (!file_exists($file)) {
            return "Error: Template {$template_id} doesn't exist!";
        } else {
            $arrayData = json_decode(file_get_contents($file), true);
            $arrayData['pattern'][] = [
                'title' => $title,
                'regex' => $regex,
                'page_detection' => $page_detection,
                'multi_matches' => $multi_matches,
                'capture_assignment' => $capture_assignment
            ];
            file_put_contents($file, json_encode($arrayData,JSON_PRETTY_PRINT));

            //set templates
            $this->set_templates();

            return "Success: Added Pattern to Template {$template_id}";
        }
    }

    /**
     * get_template
     *
     * @param string $template_id id of template
     *
     * @return array template as array to paste into class
     *
     */
    public function get_template(string $template_id)
    {
        //set templates
        $this->set_templates();

        return $this->templates[$template_id];
//        return "'{$template_id}' => " . var_export($this->templates[$template_id], true);
    }

    /**
     * delete template
     *
     * @param string $template_id id of template
     *
     * @return bool true if success
     *
     */
    public function delete_template(string $template_id) {
        $file = dirname(__FILE__, 2)."/templates/".$template_id.".json";
        return unlink($file);
    }

    /**
     * get_sample_output
     *
     * @param string $file_path (absolute or relative) path of file to get sample output
     *
     * @param bool $file_path_relative true if file-path is relative to class-file. else filepath is absolute. Default: path is absolute
     *
     * @param string $mode mode to transfer file:
     * @param string $mode >'auto':try pdftotext first and ocr next if pdftotext was not successfull
     * @param string $mode >'txt':try pdftotext only (every pdf that has text-layers)
     * @param string $mode >'ocr':try ocr only (usefull for scanned pdf-files)
     *
     *
     * @param array $demand_pages array with pages to convert. Default: ['a']
     * @param array $demand_pages > 'a' = all pages
     * @param array $demand_pages > 1,2,3 = n-page
     * @param array $demand_pages > 'l' = last page
     *
     * @param int $ocr_density density of png-file that will be created to read text in (higher densities are more accurate but lead to longer loading times). Default:150
     *
     * @param int $ocr_psm PSM-mode used in tesseract OCR (possible: 0-13). Default:6
     *
     * @param int|null $ocr_oem oem-value of tesseract request. Default: null  (for more: https://muthu.co/all-tesseract-ocr-options/)
     *
     * @param string $ocr_lang OCR traineddata language (f.e. 'eng','deu'). Find traineddata-language-files in shell: 'tesseract --list-langs'. Default: 'deu'
     *
     * @return string return of conversion as string
     */
    public function get_sample_output(string $file_path = "/../docs/sample-bill.pdf", bool $file_path_relative = true, string $demand_pages = "a", string $mode = "auto", int $ocr_density = 150, int $ocr_psm = 6, int $ocr_oem = null, string $ocr_lang = 'eng')
    {

        //check file path
        $file_path = $this->check_file_path($file_path, $file_path_relative, ["pdf"]);
        if (array_keys($file_path)[0] === 'error') {
            return "Error: " . $file_path['error'];
        } else {
            $file_path = $file_path['success'];
        };

        //transfer file to text
        switch ($mode) {
            case "txt":
                $return = $this->run_pdftotext($file_path, [$demand_pages]);
                break;
            case "ocr":
                $return = $this->run_ocr($file_path, [$demand_pages], $ocr_density, $ocr_psm, $ocr_oem, $ocr_lang);
                break;
            default:
                //try pdftotext
                $return = $this->run_pdftotext($file_path, [$demand_pages]);

                //if pdftotext-document is empty run tesseract ocr
                if (array_keys($return)[0] === 'success' && preg_match_all("/\s/", $return['success'][$demand_pages]) == strlen($return['success'][$demand_pages])) {
                    $return = $this->run_ocr($file_path, [$demand_pages], $ocr_density, $ocr_psm, $ocr_oem, $ocr_lang);
                }
        }

        //return
        if (array_keys($return)[0] === 'error') {
            return "Error: " . $return['error'];
        } else {
            return $return['success'][$demand_pages];
        }
    }

    /**
     * convert_folder
     *
     * @param string $directory (absolute or relative) path of directory to convert files from
     *
     * @param bool $file_path_relative true if folder-path is relative to class-file. else folder-path is absolute. Default: path is absolute
     *
     * @param bool $delete_files true if files should be deleted after successful conversion. Default: false
     *
     * @param string $mode mode to transfer file:
     * @param string $mode >'auto':try pdftotext first and ocr next if pdftotext was not successfull
     * @param string $mode >'txt':try pdftotext only (every pdf that has text-layers)
     * @param string $mode >'ocr':try ocr only (usefull for scanned pdf-files)
     *
     * @param int $ocr_density density of png-file that will be created to read text in (higher densities are more accurate but lead to longer loading times). Default:150
     *
     * @param int $ocr_psm PSM-mode used in tesseract OCR (possible: 0-13). Default:6
     *
     * @param int|null $ocr_oem oem-value of tesseract request. Default: null  (for more: https://muthu.co/all-tesseract-ocr-options/)
     *
     * @param string $ocr_lang OCR traineddata language used to detect document pattern (f.e. 'eng','deu'). Find traineddata-language-files in shell: 'tesseract --list-langs'. Default: 'deu'
     *
     * @return array return of conversion as array
     */
    public function convert_folder(string $directory = "/../docs/", bool $file_path_relative = false, bool $delete_files = false, string $mode = "auto", int $ocr_density = 150, int $ocr_psm = 6, int $ocr_oem = null, string $ocr_lang = 'eng')
    {

        //check file path
        $directory = $this->check_file_path($directory, $file_path_relative);
        if (array_keys($directory)[0] === 'error') {
            return "Error: " . $directory['error'];
        } else {
            $directory = $directory['success'];
        }

        //get files in filepath
        $files = array_merge(glob($directory . '/*.pdf'), glob($directory . '/*.PDF'));  //if only pdf files
//        $files = glob($directory."/*"); //if all files

        //loop through files
        $return = [];
        foreach ($files as $file) {
            $file_name = basename($file);
            $file_return = $this->convert_file($file, false, $delete_files, $mode, $ocr_density, $ocr_psm, $ocr_oem, $ocr_lang);
            if (array_keys($file_return)[0] === 'error') {
                $this->error_logs .= "\nError while converting {$file_name}: " . $file_return['error'];
            } else {
                $return[$file_name] = $file_return['success'];
            }
        }

        //return
        return $return;
    }

    /**
     * convert_file
     *
     * @param string $file_path (absolute or relative) path of file to convert
     *
     * @param bool $file_path_relative true if file-path is relative to class-file. else filepath is absolute. Default: path is absolute
     *
     * @param bool $delete_file true if file should be deleted after successful conversion. Default: false
     *
     * @param string $mode mode to transfer file:
     * @param string $mode >'auto':try pdftotext first and ocr next if pdftotext was not successfull
     * @param string $mode >'txt':try pdftotext only (every pdf that has text-layers)
     * @param string $mode >'ocr':try ocr only (usefull for scanned pdf-files)
     *
     * @param int $ocr_density density of png-file that will be created to read text in (higher densities are more accurate but lead to longer loading times). Default:150
     *
     * @param int $ocr_psm PSM-mode used in tesseract OCR (possible: 0-13). Default:6
     *
     * @param int|null $ocr_oem oem-value of tesseract request. Default: null  (for more: https://muthu.co/all-tesseract-ocr-options/)
     *
     * @param string $ocr_lang OCR traineddata language used to detect document pattern (f.e. 'eng','deu'). Find traineddata-language-files in shell: 'tesseract --list-langs'. Default: 'deu'
     *
     * @return array return of conversion as array
     */
    public function convert_file(string$file_path = "/../docs/", bool$file_path_relative=false, bool$delete_file=false, string$mode="auto", int$ocr_density=150, int$ocr_psm=6, int$ocr_oem=null, string$ocr_lang='eng')
    {

        $file_name = basename($file_path);

        //reset doc-data
        $this->doc_template = [];
        $this->doc_ptt = [];
        $this->doc_ocr = [];

        //check file path
        $file_path = $this->check_file_path($file_path, $file_path_relative,["pdf","PDF"],$delete_file);
        if (array_keys($file_path)[0] === 'error') {
            $log = "Error while converting {$file_name}: " . $file_path['error'];
            $this->write_log_file($log);
            return ["error" => $file_path['error']];
        } else {
            $file_path = $file_path['success'];
        }

        //find matching pattern
        $temp = $this->detect_doc_template($file_path, $mode, $ocr_density, $ocr_psm, $ocr_oem, $ocr_lang);
        if (array_keys($temp)[0] === 'error') {
            if ($delete_file) {
                unlink($file_path);
            }
            $log = "Error while converting {$file_name}: " . $temp['error'];
            $this->write_log_file($log);
            return ["error" => $temp['error']];
        }

        //loop through patterns
        $return['title'] = $this->doc_template['title'];
        $lang = $this->doc_template['language'];

        foreach ($this->doc_template['pattern'] as $n_temp) {
            $return_n = $this->detect_pattern_in_doc($file_path, $n_temp, $mode, $ocr_density, $ocr_psm, $ocr_oem, $lang);
            if (array_keys($return_n)[0] === 'error') {
                if ($delete_file) {
                    unlink($file_path);
                }
                $log = "Error while converting {$file_name}: " . $return_n['error'];
                $this->write_log_file($log);
                return ["error" => $return_n['error']];
            } else {
                $key = $n_temp['title'];
                if (key_exists($key,$return) && !empty($return_n['success'])) {
                    $array_key = $return[$key];
                    $return[$key] = array_merge($array_key,$return_n['success']);
                } elseif (key_exists($key,$return) && empty($return_n['success'])) {
                    continue;
                } else {
                    $return = array_merge($return,[$key => $return_n['success']]);
                }
            }
        }

        //return
        if ($delete_file) {
            unlink($file_path);
        }
        $log = "Conversion of {$file_name} successful:\n".json_encode($return, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $this->write_log_file($log);
        return ['success' => $return];
    }

    /**
     * get_error_logs
     *
     * @return string logfile as text
     */
    public function get_error_logs() {
        echo "\n\n\n\n-----------------------------------------------------------------------------------\nERROR-LOGS\n-----------------------------------------------------------------------------------\n";
        echo $this->error_logs;
    }

    /**
     * get text output
     *
     * @param string $mode mode of text
     * @param string $mode >'all': text output of pdftotext and ocr
     * @param string $mode >'txt': text output of pdftotext
     * @param string $mode >'ocr': text output of ocr
     *
     * @return string log as text
     */
    public function get_text_output(string $mode="all") {
        echo "\n\n\n\n-----------------------------------------------------------------------------------\nTEXT-OUTPUT\n-----------------------------------------------------------------------------------\n";
        if ($mode==="all" || $mode==="txt") {
            echo "\n---------------\nPDF-TO-TEXT:\n---------------\n\n";
            print_r($this->doc_ptt);
        }
        if ($mode==="all" || $mode==="ocr") {
            echo "\n---------------\nOCR:\n---------------\n\n";
            print_r($this->doc_ocr);
        }
    }

    //------------------------------------------------------------------------------------------------------------------
    // CONVERSION MODELS
    //------------------------------------------------------------------------------------------------------------------
    /**
     * run_pdftotext
     *
     * @param string $file_path (absolute)path of file
     * @param array $demand_pages array with pages to convert. Default: ['a']
     * @param array $demand_pages > 'a' = all pages
     * @param array $demand_pages > 1,2,3 = n-page
     * @param array $demand_pages > 'l' = last page
     *
     * @return array key = error; value = string with error message | key = success; value = array with all requestes $pages as key and their output-text as value
     */
    private function run_pdftotext(string$file_path, array$demand_pages=['a'])
    {

        //check syspath of pdftotext and if Poppler-Software-Suite is installed (necessary to convert pdf to text and get information about number of pages in file)
        if (!isset($this->ptt_syspath_pdftotext)) {
            $shell_request = $this->run_shell_command('which pdftotext');
            if (array_keys($shell_request)[0] === 'error') {
                return ["error" => "Please install Poppler-Software-Suite via homebrew: 'brew install poppler'"];
            } elseif (array_keys($shell_request)[0] === 'success') {
                $this->ptt_syspath_pdftotext = $shell_request['success'];
            }
        }
        //check syspath of pdfinfo and if Poppler-Software-Suite is installed (necessary to get number of pdf-pages)
        if (!isset($this->syspath_pdfinfo)) {
            $shell_request = $this->run_shell_command('which pdfinfo');
            if (array_keys($shell_request)[0] === 'error') {
                return ["error" => "Please install Poppler-Software-Suite via homebrew: 'brew install poppler'"];
            } elseif (array_keys($shell_request)[0] === 'success') {
                $this->syspath_pdfinfo = $shell_request['success'];
            }
        }

        //get number of pages
        $shell_request = $this->get_number_of_pages($file_path);
        if (array_keys($shell_request)[0] === 'error') {
            return ["error" => $shell_request['error']];
        } elseif (array_keys($shell_request)[0] === 'success') {
            $pages = $shell_request['success'];
        }

        //sort demand-pages
        $demand_pages = $this->sort_demand_pages($demand_pages);

        //loop though demand pages
        $return = [];
        foreach ($demand_pages as $e) {

            //-------------------------------------------------
            //exception if demanded page is corrupt
            //-------------------------------------------------
            if (is_int($e) && $e > $pages || is_int($e) && $e <= 0 || (!is_int($e) && $e !== 'a' && $e !== 'l')) {
                continue;
            }

            //-------------------------------------------------
            //set $page
            //-------------------------------------------------
            $page_placeholder = "";
            //all pages
            if ($e === 'a') {
                $page_placeholder = "";
            } //n-page
            elseif (is_int($e)) {
                $page_placeholder = "-f " . $e . " -l " . $e;
            } //last page
            elseif ($e === 'l') {
                $page_placeholder = "-f " . $pages . " -l " . $pages;
            }

            //-------------------------------------------------
            //get text
            //-------------------------------------------------
            //if only one page in file and 'a' is not set yet
            if (isset($return['a']) && $pages === 1) {
                $return[$e] = $return['a'];

                // if file has several pages
            } else {
                $escaped_pdf_path = escapeshellarg($file_path);
                $shell_request = $this->run_shell_command("{$this->ptt_syspath_pdftotext} -layout {$page_placeholder} {$escaped_pdf_path} -");
                if (array_keys($shell_request)[0] === 'error') {
                    return ["error" => "Was not able to run pdftotext on document: " . basename($file_path) . "."];
                } elseif (array_keys($shell_request)[0] === 'success') {
                    $return[$e] = $shell_request['success'];
                }
            }
        }

        //return
        return ['success' => $return];
    }

    /**
     * run_ocr
     *
     * @param string $file_path (absolute)path of file
     * @param array $demand_pages array with pages to convert. Default: ['a']
     * @param array $demand_pages > 'a' = all pages
     * @param array $demand_pages > 1,2,3 = n-page
     * @param array $demand_pages > 'l' = last page
     * @param int $density density of converted png file. Default: 150
     * @param int $psm psm-value of tesseract request. Default: 6  (for more: https://muthu.co/all-tesseract-ocr-options/)
     * @param int|null $oem oem-value of tesseract request. Default: null  (for more: https://muthu.co/all-tesseract-ocr-options/)
     * @param string $lang language traineddata (find in shell: tesseract --list-langs). Default: "deu"
     *
     * @return array key = error; value = string with error message | key = success; value = array with all requestes $pages as key and their output-text as value
     */
    private function run_ocr(string$file_path, array$demand_pages=['a'], int$density=150, int$psm=6, int$oem=null, string$lang="deu")
    {

        //check syspath of pdfinfo and if Poppler-Software-Suite is installed (necessary to get number of pdf-pages)
        if (!isset($this->syspath_pdfinfo)) {
            $shell_request = $this->run_shell_command('which pdfinfo');
            if (array_keys($shell_request)[0] === 'error') {
                return ["error" => "Please install Poppler-Software-Suite via homebrew: 'brew install poppler'"];
            } elseif (array_keys($shell_request)[0] === 'success') {
                $this->syspath_pdfinfo = $shell_request['success'];
            }
        }
        //check if ImageMagick-Software-Suite is installed (necessary to convert pdf->png)
        if (!isset($this->ocr_syspath_convert)) {
            $shell_request = $this->run_shell_command('which convert');
            if (array_keys($shell_request)[0] === 'error') {
                return ["error" => "Please install ImageMagick-Software-Suite via homebrew: 'brew install imagemagick'"];
            } elseif (array_keys($shell_request)[0] === 'success') {
                $this->ocr_syspath_convert = $shell_request['success'];
            }
        }
        //check if TesseractOCR is installed (necessary to read and interpret png file)
        if (!isset($this->ocr_syspath_tesseract)) {
            $shell_request = $this->run_shell_command('which tesseract');
            if (array_keys($shell_request)[0] === 'error') {
                return ["error" => "Please install TesseractOCR via homebrew: 'brew install tesseract'"];
            } elseif (array_keys($shell_request)[0] === 'success') {
                $this->ocr_syspath_tesseract = $shell_request['success'];
            }
        }

        //get number of pages
        $shell_request = $this->get_number_of_pages($file_path);
        if (array_keys($shell_request)[0] === 'error') {
            return ["error" => $shell_request['error']];
        } elseif (array_keys($shell_request)[0] === 'success') {
            $pages = $shell_request['success'];
        }

        //sort demand-pages
        $demand_pages = $this->sort_demand_pages($demand_pages);

        //loop though demand pages
        $return = [];
        foreach ($demand_pages as $e) {

            //-------------------------------------------------
            //exception if demanded page is corrupt
            //-------------------------------------------------
            if (is_int($e) && $e > $pages || is_int($e) && $e <= 0 || (!is_int($e) && $e !== 'a' && $e !== 'l')) {
                continue;
            }

            //-------------------------------------------------
            //set $page
            //-------------------------------------------------
            $page_placeholder = "";
            //all pages
            if ($e === 'a') {
                $page_placeholder = "";
            } //n-page
            elseif (is_int($e)) {
                $page_placeholder = "[" . ($e - 1) . "]";
            } //last page
            elseif ($e === 'l') {
                $page_placeholder = "[" . ($pages - 1) . "]";
            }

            //-------------------------------------------------
            //get text
            //-------------------------------------------------
            //if only one page in file and 'a' is set yet
            if (isset($return['a']) && $pages === 1) {
                $return[$e] = $return['a'];

                // if file has several pages
            } else {
                $escaped_pdf_path = escapeshellarg($file_path);
                $temp_image_path = $this->temp_folder . '/temp_image.png';
                $escaped_temp_image_path = escapeshellarg($temp_image_path);

                $i_max = $e === "a" ? $pages : 1;  //number of runs
                $text = "";
                for ($i = 1; $i <= $i_max; $i++) {

                    //convert pdf->png with ImageMagick-Software-Suite
                    $page_placeholder = $e === "a" ? "[" . ($i - 1) . "]" : $page_placeholder;
                    $shell_request = $this->run_shell_command("{$this->ocr_syspath_convert} -density {$density} -trim {$escaped_pdf_path}{$page_placeholder} {$escaped_temp_image_path}");
                    if (array_keys($shell_request)[0] === 'error') {
                        return ["error" => "Was not able to run ImageMagick's convert on document: " . basename($file_path) . "."];
                    }

                    //read png with TesseractOCR
                    $psm_o = " --psm {$psm}";
                    $oem_o = $oem === null ? "" : " --oem {$oem}";
                    $shell_request = $this->run_shell_command("{$this->ocr_syspath_tesseract} {$escaped_temp_image_path} stdout -l {$lang}{$psm_o}{$oem_o}");
                    unlink($temp_image_path);
                    if (array_keys($shell_request)[0] === 'error') {
                        return ["error" => "Was not able to run TesseractOCR on document: " . basename($file_path) . ".\n\nMore:\n" . ($shell_request['error'])];
                    } elseif (array_keys($shell_request)[0] === 'success') {
                        $side_break = $e === "a" && $i > 1 ? "\n\n" : "";
                        $text .= $side_break . $shell_request['success'];
                    }
                }
            }
            $return[$e] = $text;
        }

        //return
        return ['success' => $return];
    }

    //------------------------------------------------------------------------------------------------------------------
    // DETECT TEXT PATTERN
    //------------------------------------------------------------------------------------------------------------------
    /**
     * detect_doc_template
     *
     * @param string $file_path (absolute)path of file
     *
     * @param string $mode mode to transfer file:
     * @param string $mode >'auto':try pdftotext first and ocr next if pdftotext was not successfull
     * @param string $mode >'txt':try pdftotext only (every pdf that has text-layers)
     * @param string $mode >'ocr':try ocr only (usefull for scanned pdf-files)
     *
     * @param int $ocr_density density of png-file that will be created to read text in (higher densities are more accurate but lead to longer loading times). Default:150
     *
     * @param int $ocr_psm PSM-mode used in tesseract OCR (possible: 0-13). Default:6
     *
     * @param int|null $ocr_oem oem-value of tesseract request. Default: null  (for more: https://muthu.co/all-tesseract-ocr-options/)
     *
     * @param string $ocr_lang OCR traineddata language (f.e. 'eng','deu'). Find traineddata-language-files in shell: 'tesseract --list-langs'. Default: 'deu'
     *
     *
     * @return array key = error; value = string with error message | key = success; value = template_id
     */
    private function detect_doc_template(string$file_path, string$mode="auto", int$ocr_density=150, int$ocr_psm=6, int$ocr_oem=null, string$ocr_lang='deu')
    {
        //error if no templates set
        if (count($this->templates)===0) {
            return ["error" => "No templates available"];
        }

        //----------------------
        //get demand_pages
        //----------------------
        $demand_pages = array_unique(array_column($this->templates, 'page_detection'));

        //----------------------
        //transfer file to text
        //----------------------
        switch ($mode) {
            case "txt":
            case "auto":
                $text_array = $this->run_pdftotext($file_path, $demand_pages);
                if (array_keys($text_array)[0] === 'error') {
                    return ["error" => $text_array['error']];
                } else {
                    $text_array = $text_array['success'];
                }
                $this->doc_ptt = $text_array;
                break;
            case "ocr":
                $text_array = $this->run_ocr($file_path, $demand_pages, $ocr_density, $ocr_psm, $ocr_oem, $ocr_lang);
                if (array_keys($text_array)[0] === 'error') {
                    return ["error" => $text_array['error']];
                } else {
                    $text_array = $text_array['success'];
                }
                $this->doc_ocr = $text_array;
                break;
        }

        //----------------------
        //loop through templates
        //----------------------
        $matches_array = [];
        foreach ($this->templates as $template) {

            //get data of template
            $page_det = $template['page_detection'];
            $pattern = $template['regex'];

            //if requestet page is without content and $mode=="auto"
            if ($mode==="auto" && (preg_match_all("/\s/",$text_array[$page_det]) == strlen($text_array[$page_det]))) {

                if (!isset($this->doc_ocr[$page_det])) {
                    $page_ocr = $this->run_ocr($file_path, [$page_det], $ocr_density, $ocr_psm, $ocr_oem, $ocr_lang);
                    if (array_keys($page_ocr)[0] === 'error') {
                        return ["error" => $page_ocr['error']];
                    } else {
                        $this->doc_ocr[$page_det] = $page_ocr['success'][$page_det];
                        $text_array[$page_det] = $page_ocr['success'][$page_det];
                    }
                }
            }
            $matches_array[] = preg_match_all($pattern,$text_array[$page_det]);
        }

        //sort matches array
        arsort($matches_array);

        //----------------------
        //check for errors
        //----------------------
        //best result has no matches
        if ($matches_array[array_key_first($matches_array)]===0) {
            return ["error" => "No template found"];
        }

        //second result==best result
        $matches_sorted = array_values($matches_array);
        if (count($matches_array)>1 && ($matches_sorted[0]==$matches_sorted[1])) {
            return ["error" => "No clearly assignable template found"];
        }

        //----------------------
        //set template
        //----------------------
        $templates = array_values($this->templates);
        $this->doc_template = $templates[array_key_first($matches_array)];

        //return
        return ["success" => array_keys($this->templates)[array_key_first($matches_array)]];
    }

    /**
     * detect_pattern_in_doc
     *
     * @param string $file_path (absolute)path of file
     *
     * @param array $pattern array with information about pattern
     *
     * @param string $mode mode to transfer file:
     * @param string $mode >'auto':try pdftotext first and ocr next if pdftotext was not successfull
     * @param string $mode >'txt':try pdftotext only (every pdf that has text-layers)
     * @param string $mode >'ocr':try ocr only (usefull for scanned pdf-files)
     *
     * @param int $ocr_density density of png-file that will be created to read text in (higher densities are more accurate but lead to longer loading times). Default:150
     *
     * @param int $ocr_psm PSM-mode used in tesseract OCR (possible: 0-13). Default:6
     *
     * @param int|null $ocr_oem oem-value of tesseract request. Default: null  (for more: https://muthu.co/all-tesseract-ocr-options/)
     *
     * @param string $ocr_lang OCR traineddata language (f.e. 'eng','deu'). Find traineddata-language-files in shell: 'tesseract --list-langs'. Default: 'deu'
     *
     *
     * @return array key = error; value = string with error message | key = success; value = array with elements that matches pattern
     */
    private function detect_pattern_in_doc(string$file_path, array$pattern, string$mode="auto", int$ocr_density=150, int$ocr_psm=6, int$ocr_oem=null, string$ocr_lang='deu') {

        $demand_pages = $pattern['page_detection'];
        //----------------------
        //get text
        //----------------------
        $text = "";
        //ptt
        if ($mode==="txt" || $mode==="auto") {
            if (isset($this->doc_ptt[$demand_pages])) {
                //get text from class-array
                $text = $this->doc_ptt[$demand_pages];
            } else {
                //get text from document
                $text_array = $this->run_pdftotext($file_path, [$demand_pages]);
                if (array_keys($text_array)[0] === 'error') {
                    return ["error" => $text_array['error']];
                } else {
                    $text = $text_array['success'][$demand_pages];
                    $this->doc_ptt[$demand_pages] = $text_array['success'][$demand_pages];
                }
            }
            //check if text is empty
            if (preg_match_all("/\s/",$text) == strlen($text)) {
                $mode = "ocr";
            }
        }
        //ocr
        if ($mode==="ocr") {
            if (isset($this->doc_ocr[$demand_pages])) {
                //get text from class-array
                $text = $this->doc_ocr[$demand_pages];
            } else {
                //get text from document
                $text_array = $this->run_ocr($file_path, [$demand_pages]);
                if (array_keys($text_array)[0] === 'error') {
                    return ["error" => $text_array['error']];
                } else {
                    $text = $text_array['success'][$demand_pages];
                    $this->doc_ocr[$demand_pages] = $text_array['success'][$demand_pages];
                }
            }
        }

        preg_match_all($pattern['regex'],$text,$matches,PREG_SET_ORDER);

        // loop through matches
        foreach ($matches as $k_match => $captures) {

            $r_captures = [];

            //loop through captures
            foreach ($captures as $k_capture => $n_capture) {

                //continue if first capture
                if ($k_capture===0) {continue;}

                //if only one capture
                if ($pattern['capture_assignment']===null) {
                    $r_captures = $n_capture;
                    break;
                }
                //if multiple captures
                else {
                    $r_captures[$pattern['capture_assignment'][$k_capture-1]] = $n_capture;
                }
            }

            // if only one match
            if (!$pattern['multi_matches']) {
                $r_matches = $r_captures;
                break;
            }
            // if multiple matches
            else {
                $r_matches[$k_match] = $r_captures;
            }
        }

        $r_matches = count($matches)===0 ? null : $r_matches;

        //outputs
//        echo "\n\n\n-------------------------------------------------------------------------------------\n".$pattern['title']."\n-------------------------------------------------------------------------------------\n";
//        echo "\nmode: ".$mode;
//        echo "\npages to look up: ".$demand_pages;
//        echo "\npattern: ".$pattern['regex'];
//        echo "\n\n--------------\nTEXT:\n--------------\n".$text."\n--------------\n";
//        echo "\nreturn:\n\n";print_r($r_matches);

        //return
        return ["success" => $r_matches];
    }

    //------------------------------------------------------------------------------------------------------------------
    // SUPPORT METHODS
    //------------------------------------------------------------------------------------------------------------------
    /**
     * run_shell_command
     *
     * @param string $command shell command
     *
     * @return array key = error; value = string with error message | key = success; value = return of shell command
     */
    private function run_shell_command(string $command)
    {
        $env = ['PATH' => $this->path_env];
        $process = Process::fromShellCommandline($command, null, $env);
        $process->run();

        $return = [];
        if ($process->isSuccessful()) {
            $return['success'] = trim($process->getOutput());
        } else {
            $return['error'] = $process->getErrorOutput();
        }
        return $return;
    }

    /**
     * check_file_path
     *
     * @param string $file_path (absolute or relative) path of file or folder
     * @param bool $file_path_relative true if file-path is relative to class-file. else filepath is absolute. Default: path is absolute
     * @param array|null $suffix check if file of path has one of the suffixes in array. if $suffix=null -> don't check. Default: null
     *
     * @return array key = error; value = string with error message | key = success; value = string with absolute path of file or folder
     */
    private function check_file_path(string $file_path, bool $file_path_relative = false, array $suffix = null, bool $delete_file = false)
    {
        // get file path
        if ($file_path_relative) {
            $path1 = dirname(__FILE__);
            $path2 = $file_path;
            $file_path_new = realpath($path1 . $path2);
        } else {
            $file_path_new = file_exists($file_path) ? $file_path : "";
        }

        // if file path doesn't exist
        if (empty($file_path_new)) {
            return ['error' => "filepath {$file_path_new} does not exist!"];
        }

        //check if file-suffix is given (for single files)
        if ($suffix !== null && !in_array(pathinfo($file_path_new, PATHINFO_EXTENSION),$suffix)) {
            if ($delete_file) {
                unlink($file_path_new);
            }
            return ['error' => "File is none of the following file-formats: " . implode(",",$suffix) . "."];
        }

        // if file
        return ['success' => $file_path_new];
    }

    /**
     * sort_demand_pages
     *
     * @param array $demand_pages array with pages to convert
     *
     * @return array all demands sorted bei string/int -> strings alphabetical -> integer numerical. strings with numbers will be converted to integer
     */
    private function sort_demand_pages(array $demand_pages)
    {
        array_walk($demand_pages, function (&$value) {
            if (is_numeric($value)) {
                $value = (int)$value;
            }
        });

        usort($demand_pages, function ($a, $b) {
            if (is_int($a) && is_int($b)) {
                return $a <=> $b;
            }
            if (is_string($a) && is_string($b)) {
                return strcmp($a, $b);
            }
            return is_string($a) ? -1 : 1;
        });
        return $demand_pages;
    }

    /**
     * get number of pages
     *
     * @param string $file_path string with (absolute)-filepath
     *
     * @return array array key = error; value = string with error message | key = success; value = number of pages
     */
    private function get_number_of_pages(string $file_path)
    {
        $escaped_pdf_path = escapeshellarg($file_path);
        $shell_request = $this->run_shell_command("{$this->syspath_pdfinfo} {$escaped_pdf_path} | grep Pages: | awk '{print $2}'");
        if (array_keys($shell_request)[0] === 'error') {
            return ["error" => "Please check Poppler-Software-Suite. Number of pages of document could not be detected"];
        } elseif (array_keys($shell_request)[0] === 'success') {
            return $shell_request;
        }
    }

    /**
     * get templates
     *
     * @return array array with all templates from folder templates
     */
    private function set_templates(){
        $folder = dirname(__FILE__, 2)."/templates";
        $template_files = glob($folder . "/*.json");

        $templates = [];
        foreach ($template_files as $temp_n) {
            $data_array = json_decode(file_get_contents($temp_n), true);
            $key = pathinfo($temp_n, PATHINFO_FILENAME);
            $templates[$key] = $data_array;
        }
        $this->templates = $templates;
        return $templates;
    }

    /**
     * @param string $log text to log in file
     *
     * @return void
     */
    private function write_log_file(string $log="") {
        $log_file = $this->log_folder."/read_pdf_docs.txt";

        //check if file exist, create if not
        if (!file_exists($log_file)) {
            file_put_contents($log_file, '');
        }

        //set datetime
        $utcTimestamp = gmdate('Y-m-d H:i:s');

        //set new log
        $log = "+--------------------------------------------------------------------------------+\n".$utcTimestamp."\n\n".$log."\n";

        //add log to file
        file_put_contents($log_file, $log . PHP_EOL, FILE_APPEND);
    }
}