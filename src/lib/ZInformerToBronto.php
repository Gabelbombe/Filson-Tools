<?php
/*
 * Take a mixed zInformer export file with data such as:

 "DATE",EMAIL,SOURCE,FIRSTNAME,LASTNAME,ZIP
   "2014-05-09","Cunningham_Kelly@hotmail.com","pop-up-flea-frd","undefined","undefined","undefined"
   "2014-05-09","steve.schiller@gmail.com","pop-up-flea-frd","undefined","undefined","undefined"
 "DATE",FIRSTNAME,MIDDLEINITIAL,LASTNAME,EMAIL,CITY,STATE,ZIP,COUNTRY,ADDRESS1,ADDRESS2
   "2014-05-09","Horst","","Mueller","shipsone@aol.com","St. LAMBERT","QC","J4R 1R3","CA","1225 Victoria Avenue #7",""

 And normalize the column ordering and data values for an import or additional processing...
 */

$importDelimiter = ",";

$newRecordsetDelimiter = "DATE";
$isNewRecordset = false;

$inputFile = getcwd() . '/email_signup-NEW.csv';
$outputFile = getcwd() . '/email_signup-NORMALIZED.csv';

// This associative array contains the header columns as keys, and the actual indices as the value. If the index value is '-1', then there was no value found...
$outputHeaderLine = [
    'EMAIL' => -1,
    'FIRSTNAME' => -1,
    'LASTNAME' => -1,
    'ADDRESS1' => -1,
    'ADDRESS2' => -1,
    'CITY' => -1,
    'STATE' => -1,
    'ZIP' => -1,
    'COUNTRY' => -1,
    'SOURCE' => -1
];

$rowHeaderLine = null;
$limitOnImport = -1;

// Command Line Parameters can override defaults
if(count($argv) > 1)
{
    // Check for an overriding input file name
    if(!empty($argv[1]))
    {
        // Is the user asking for help?
        if(in_array($argv[1], ['?', 'help', '-h', '--help']))
        {
            echo "\n";
            echo 'Program Usage: ' . $argv[0] . ' <relative path to input> <limit on import>';
            echo "\n"; echo "\n\t";
            echo '<relative path to input>: The program looks for the file in the current working directory.' . "\n\n\t\t" .
                'Example: user_images_to_import.txt (evaluates to ./user_images_to_import.txt)' . "\n\t\t" .
                'Example: data/user_images_to_import.txt (evaluates to ./data/user_images_to_import.txt)';
            echo "\n\n";
            echo 'Example Usage: ';
            echo "\n\n\t";
            echo $argv[0] . ' user_images_to_import.txt    - Convert ALL records from the user_images_to_import.txt file' . "\n\t";
            echo "\n";
            return;
        }

        if ($argv[1] == '-i' || false !== (strpos($argv[1] ,'--input')))
        {
            if ($argv[1] == '-i' && ! empty($argv[2]))
            {
                $inputFile = getcwd() . '/' . $argv[2];

                    unset($argv[2]);

                $argv = array_values($argv); // reindex
            }
        }

        else
        {
            // Otherwise we have a hopefully proper file path
            $inputFile = getcwd() . '/' . $argv[1];
        }

        if($argv[2] != '')
        {
            $limitOnImport = $argv[2];
        }
    }
}
if(! file_exists($inputFile))
{
    die("\n\n" . 'Could NOT read from "' . $inputFile . '". Quitting!' . "\n\n");
}

// Read in the file and loop through it
$output = '';
$input = fopen($inputFile, 'r');
$numProcessed = 0;
$skipDelimiter = false;
$skippedRecord = false;

if(!feof($input))
{
    // output header line
    foreach($outputHeaderLine as $name => $index)
    {
        if($output != '')
        {
            $output .= ',';
        }
        $output .= $name;
    }
    $output .= "\n";
}

while(!feof($input))
{
    if($limitOnImport != -1 && $numProcessed >= $limitOnImport)
    {
        break;
    }
    $dataOld = fgetcsv($input, 200, $importDelimiter);

    if($dataOld[0] === 'DATE')
    {
        $isNewRecordset = true;
        $skipDelimiter = true;
    }

    else
    {
        $isNewRecordset = false;
    }

    if(!$isNewRecordset)
    {
        $valueToSave = '';
        foreach($outputHeaderLine as $name => $index)
        {
            if($name === 'EMAIL' && (filter_var(trim($dataOld[$index]), FILTER_VALIDATE_EMAIL) === FALSE))
            {
                $skippedRecord = true;
                echo "Skipping invalid email of '" . $dataOld[$index] . "'\n";
                break;
            }
            if($output != '' && !$skipDelimiter)
            {
                $output .= ',';
            }

            if($skipDelimiter)
            {
                $skipDelimiter = false;
            }

            if($index == -1)
            {
                $output .= '""';	// empty value
            }

            else
            {
                if(count($dataOld) > $index)
                {
                    $valueToSave = trim($dataOld[$index]);
                }

                else
                {
                    $valueToSave = 'undefined';
                }

                if($valueToSave === 'undefined')
                {
                    $output .= '""';	// empty value
                }

                else if($valueToSave !== '')
                {
                    $output .= '"' . $valueToSave . '"';
                }

                $valueToSave = '';	// reset
            }
        }

        if(!$skippedRecord)
        {
            $output .= "\n";
        }

        $skipDelimiter = true;
        $skippedRecord = false;
        $numProcessed++;
    }

    else
    {
        // Reset the mapping
        foreach($outputHeaderLine as $name => $index)
        {
            $outputHeaderLine[$name] = -1;
        }

        // Map the header columns and the indices
        foreach($dataOld as $index => $name) {
            if(array_key_exists($name, $outputHeaderLine)) {
                $outputHeaderLine[$name] = $index;
            }
        }
    }
}
fclose($input);

if(file_put_contents($outputFile, $output) !== FALSE) {
    echo 'Wrote merged records to "' . $outputFile . '"' . "\n";
}
?>