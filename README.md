# qPCR-analysis

This script can be used after LinRegPCR analysis to plot your data
 in a clear way and automate the relative gene expression calculations.
Data coming out of the analysis will consist of PDF files containing graphs
 and Excel files containing the data behind these graphs.

Before using this script, use the "Calculate baselines" function in LinRegPCR.
Remove rows with data from empty wells and name your samples as following:
  `sample_primer` (e.g., `Control1_GAPDH`).

The Excel file provided to the script should be an `.xlsx` file.
The sheet containing the data should be named `Data`.
LinRegPCR will then create `Data_output` and `Data_compact`.
The sheet containing the data for the melting curves
 should be named `Melting curves`.
The melting curves sheet is not mandatory.



## Installation

qPCR-analysis requires the following python libraries:
- argparse
- math
- matplotlib.pyplot
- natsort
- openpyxl
- os
- pandas
- statistics
- sys

***TODO: Add versions.***


### Web interface

qPCR analysis can also be operated from a web browser.
A PHP-based web interface is included in the `web-wrapper` directory.
To enable this interface, install a webserver such as Apache, enable the PHP
 module, and add this project to a location where the webserver can reach it.
Make sure the `web-wrapper/data` directory is writable by the webserver user.

If you are not using an Apache webserver, make sure you protect the
data directory from direct downloads, to ensure the safety of the data.
For Apache users, a `.htaccess` file is already in place to block access.

#### Maintenance of the web interface

For every analysis, a new directory is created
 in the `web-wrapper/data` directory.
To avoid filling up your drive, make sure old data folders are removed.
You can use the cronjob below for this purpose.
Make sure the cron will run as the user of the webserver,
 and that you replace `path` with the appropriate directory.

```
0 0 * * * find /path/data/ -type d -iname 1\* -mtime +0 | xargs rm -r
```

This removes data folders older than 24 hours, every night at midnight.
Feel free to change the settings; this cron can easily be run at any given time,
 run more than once a day, or less frequently, e.g., once a week or month.
If you want the cronjob to create output, add a "v" at the end of the command,
 i.e., `xargs rm -rv`.



## How to use qPCR-analysis


### Through the terminal

You can invoke qPCR-analysis by running:

```
python3 qpcr_analysis --input <input file> \
                      --genes <gene> [<gene> [...]] \
                      --controls <control> [<control> [...]] 
```

This will create three directories in the current directory;
 `Input`, `Figures`, and `Data`.
qPCR should take a little less than a minute to process your data.
Results will be created in all three directories.

***TODO: Explain each file?***

If you'd like to have a list of primers and samples listed in your input file,
 you can invoke qPCR-analysis by running:
```
python3 qpcr_analysis --input <input file>
```

When invoked without any further arguments, qPCR-analysis will read out your
 spreadsheet and generate `Input/Genes.txt` and `Input/Cell_lines.txt` in the
 current directory.
These files, respectively, contain the list of primers and samples mentioned in
 your given spreadsheet.
Values from these lists can then be used for the `--genes` and `--controls`
 arguments.


### Web interface

The web interface is designed to work as intuitively as possible.
An example installation is currently available at
 [HumGen.nl/scripts/qPCR-analysis](https://humgen.nl/scripts/qPCR-analysis/).
The web interface does not require any log in, but simply asks you
 to upload the `.xlsx` input file that was obtained from LinReqPCR.
After pressing the submit button, the interface runs the qPCR-analysis script to
 extract the list of primers and samples, and then lets you choose the
 housekeeping genes and the controls cell lines.
In each case, the interface shows buttons for each possible value.
Clicking the buttons you'd like to choose, selects them.
For each, you must select at least one value.
Then, press the submit button to continue.
After selecting both at least one housekeeping gene and then at least one
 control sample, the script will take a while to execute - please wait for it
 to finish execution.
This will take a little less than a minute.
When the qPCR-analysis script is done, the webinterface will create a ZIP
 archive containing all the files (the input file, the settings, and all output
 files) and send it to the web browser for download.
If any failures occur, clear error messages will indicate at what point the
 script failed to run, so that actions can be taken to fix these.
