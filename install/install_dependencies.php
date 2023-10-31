<?php

function installHomebrewAndPackages() {
    $os = php_uname('s');
    $installScript = null;

    //mac
    if ($os === 'Darwin') {
        $installScript = './install/install-dependencies-mac.sh';

    //linux
    } elseif ($os === 'Linux') {
        $installScript = './install/install_linux.sh';
    }

    //windows
    elseif ($os === 'Windows_NT') {
        $installScript = './install/install_windows.bat';
    }

    //other
    else {
        exit("Unsupported OS: $os");
    }

    $output = null;
    $resultCode = null;

    exec("bash $installScript", $output, $resultCode);

    if ($resultCode !== 0) {
        exit("Script failed with code $resultCode");
    }

    echo implode("\n", $output);
}

installHomebrewAndPackages();