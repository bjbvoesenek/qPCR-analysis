# qPCR-analysis 2.2.1

The qPCR analysis software is designed to conveniently and intuitively plot
 amplification plots and melting curves.
In addition, it automates the calculation of the relative gene expression,
 calculates the efficiency of every primerset used,
 and plots this data in a clear way.
The software is written in Python, can be run using the command line,
 but also includes a web interface.
The web interface is also publicly available at
 https://humgen.nl/scripts/qPCR-analysis/. 

As input, both the LightCycler 480 and Bio-Rad systems are suitable,
 but these require different preparation of the Excel files.



## Table of contents
- [Installation](#installation)  
  - [Web interface](#web-interface)
    - [Maintenance of the web interface](#maintenance-of-the-web-interface)
- [How to use qPCR-analysis](#how-to-use-qpcr-analysis)
  - [Preparing the LightCycler 480 output](#preparing-the-lightcycler-480-output)
  - [Preparing the Bio-Rad output](#preparing-the-bio-rad-output)
  - [The qPCR analysis](#the-qpcr-analysis)
    - [Through the terminal](#through-the-terminal)
    - [Using the web interface](#using-the-web-interface)
- [Structure of the output](#structure-of-the-output) 
  - [The "Data" directory](#the-data-directory)
  - [The "Figures" directory](#the-figures-directory)
  - [The "Input" directory](#the-input-directory)



## Installation

qPCR-analysis has been developed with, and tested on, various versions of Python
 and the required libraries.
Other versions might work, but are not supported.
qPCR-analysis has the following requirements:
- Python (3.6.13 – 3.10.6)
- matplotlib (3.0.2 – 3.5.1)
- natsort (6.0.0 – 8.2.0)
- pandas (0.23.0 – 1.3.5)

The project includes an `requirements.txt` that can be used to quickly install
 all dependencies:

```
pip3 install -r requirements.txt
```



### Web interface

qPCR analysis can also be operated from a web browser.
A PHP-based web interface is included in the `web-wrapper` directory.
To enable this interface, install a webserver such as Apache, enable the PHP
 module, and add this project to a location where the webserver can reach it.
Make sure the `web-wrapper/data` directory is writable by the webserver user.

If you are not using an Apache webserver, make sure you protect the
 data directory from direct downloads, to ensure the safety of the data.
For Apache users, a `.htaccess` file is already in place to block access.

The web interface has been developed with, and tested on Apache 2.4.38 – 2.4.52
 and PHP 7.3.31 – 8.1.2.


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

The full workflow of using qPCR-analysis depends on your input,
 see below for instructions.
A more detailed explanation about the processing of the LightCycler 480 can be
 found [in this manual](LightCycler_480_LinRegPCR_manual.pdf).
Here, you can also find how the relative gene expression is calculated.



### Preparing the LightCycler 480 output

Before using the qPCR-analysis software,
 the data coming out of the LightCycler 480 needs to be transposed.
For this, you can use the http://humgen.nl/scripts/transpose/ website.
Upload the .txt file that was obtained by retrieving the data from the
 LightCycler 480 in the "Select the input file" field,
 set the amount of PCR cycles to 45 (or another amount if you used a different
 RT-qPCR protocol) and press "Transpose".

Open a new Excel file and make sure to double-check if the system separator
 settings are set as shown below — decimal separator set to period, and the
 thousands separator set to comma.
If the defaults are different, untick the box "Use system separators", and
 change the settings.
Restart Excel after this change.

![](doc-images/Excel_system_separators.png)

Copy the resulting text file from the transpose website to Excel, and remove the
 data from empty wells (wells of the plate where you did not pipet anything in).
Name this sheet "Data".
Name every sample as follows: "cell_primer" (e.g., "Control1_GAPDH").

![](doc-images/LinReg_Data.png)

In case the BioRad system was used, the Excel file will look slighty different,
 see below.

![](doc-images/BioRad_LinRegPCR_input.png)

Next, the data needs to be normalized in LinRegPCR.
Open LinRegPCR and click "File", then "Read from Excel".

A new window opens; select the boxes as shown below.
During RT-qPCR, a DNA-binding dye was used (Sybr Green), ds-DNA was used as
 amplification, and a LightCycler 480 was used to convert the raw data files.
In the "Book" drop down menu, you'll see the name of the Excel file you're
 working with.
LinRegPCR can only read from one Excel file at a time, so it's important to
 close all other Excel files.
In the "Sheet" drop down menu, select the tab containing the raw data from the
 Excel file ("Data").
At the bottom, enter the columns (running from "A" through "AU") and enter the
 amount of rows.
This always starts at "1", but you'll have to check your Excel file to see what
 value you'll need to put into the second box.
Press "OK".
Then, in the next screen, click "Determine Baselines" in the top left corner.

![](doc-images/LinReg_read_Excel.png)

In order to save the data to your Excel file,
 click "File", then "Save to Excel".
Use the settings as shown below.

![](doc-images/LinReg_save_to_Excel.png)

After calculating, LinRegPCR adds two new tabs to your Excel file;
 an "output" tab and a "compact" tab (shown below).
Do not change the names of these tabs or any of the columns within these tabs.
In the output tab, under "indiv PCR eff" you can find the efficiencies of the
 primers per sample.
This efficiency is calculated by quantifying the amount of newly formed amplicon
 after each PCR cycle.
So in theory, this number should be 2.00, because you expect that after each
 cycle, the amount of PCR product is doubled.
In practice, the PCR efficiency is somewhere between 1.80 – 1.90.
If the efficiency is lower than 1.80, one of the following could be the cause:
 the samples may contain PCR inhibitors (if so, use cDNA that is more diluted),
 primer design is not optimal, or inaccurate sample/master mix pipetting.
In the "compact" tab you'll still see averaged PCR efficiency values.
In case you want to remove outliers, delete the Ct value of the sample in the
 "compact" tab.

![](doc-images/LinReg_Data_compact.png)

If you exported melting curves from the LightCycler 480, copy the content
 of the .txt file to your Excel file in a new sheet named "Melting curves".
Delete the columns (both X and Sample) of empty wells.
Do not forget to do the 'Tm calling' in the LightCycler 480 software.

![](doc-images/LinReg_melting_curves.png)

From here, continue with [the qPCR analysis](#the-qpcr-analysis).



### Preparing the Bio-Rad output

Before using the qPCR-analysis software,
 the Bio-Rad data need to be prepared a bit.
Firstly, rename the sheet containing the Cq values to "Cq".
In the column "Sample", name the samples containing the mq control "mq" or "MQ".
If you exported melting curves, copy the content to a new sheet
 in the Excel file that you are going to analyze.
Name this sheet "Melting curves".

![](doc-images/BioRad_Cq.png)

![](doc-images/BioRad_melting_curves.png) 

From here, continue with [the qPCR analysis](#the-qpcr-analysis).



### The qPCR analysis

#### Through the terminal

If you'd like to have a list of primers and samples listed in your input file,
 you can ask qPCR-analysis to extract this data by running:

```
python3 qpcr_analysis.py --input <input file>
```

When invoked without any further arguments, qPCR-analysis will read out your
 spreadsheet and generate `Input/Genes.txt` and `Input/Cell_lines.txt` in the
 current directory.
These files, respectively, contain the list of primers and samples mentioned in
 your given spreadsheet.
Values from these lists can then be used for the `--genes` and `--controls`
 arguments to process your spreadsheet.

You can invoke qPCR-analysis by running:

```
python3 qpcr_analysis.py --input <input file> \
                         --genes <gene> [<gene> [...]] \
                         --controls <control> [<control> [...]] 
```

This will create three directories in the current directory;
 `Data`, `Figures`, and `Input`.
The qPCR analysis software should take a little less
 than a minute to process your data.
For a detailed description of the output files,
 see [structure of the output](#structure-of-the-output).


#### Using the web interface

The web interface is designed to work as intuitively as possible.
An example installation is currently available at
 [HumGen.nl/scripts/qPCR-analysis](https://humgen.nl/scripts/qPCR-analysis/).
The web interface does not require any log in, but simply asks you
 to upload the `.xlsx` input file that you prepared.

![](doc-images/qPCR_web_interface.png)

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
Your results will be ready and downloaded within a minute.
If any failures occur, clear error messages will indicate at what point the
 script failed to run, so that actions can be taken to fix these.



## Structure of the output

qPCR-analysis creates three folders; `Data`, `Figures`, and `Input`.


#### The "Data" directory

The `Data` folder contains Excel sheets that you can use
 to make figures for yourself, if needed.
- `Average_Ct_values.xlsx` — the average Ct per condition
- `Relative_expression_values.xlsx` — the relative gene expression per condition
- `Normalized_relative_expression_values.xlsx` — the relative gene expression per condition normalized to control


#### The "Figures" directory

The `Figures` folder contains figures generated by the script:
- `Average_Ct_bargraph.pdf` — triplicate average Ct value of each sample
- `Melting_curves_sorted.pdf` — graphs showing melting curve per sample
- `PCR_efficiency.pdf` — efficiency of the primersets *(LightCycler only)*
- `Amplification_plots_sorted.pdf` — amplification plots *(LightCycler only)*
- `Relative_expression_values.pdf` — relative expression values of each sample
- `Normalized_relative_expression_values.pdf` — normalized relative expression values of each sample

The melting curves and amplification plots are sorted in a way that every
 row is a cell line, and every column a gene.
This makes it easy to compare cell lines and see which samples are different.

![](doc-images/amplification_curves.png)

Other figures include bargraphs showing the triplicate average
Ct value of each sample, the (normalized) relative expression values of each sample,
 and, in case LightCycler data was used, the efficiency of the primersets.
Control lines are represented by gray bars, other lines by blue bars.


#### The "Input" directory

The `Input` folder contains the following files:
- `Cell_lines.txt` — a list of extracted cell lines, optional
- `Genes.txt` — a list of extracted housekeeping genes, optional
- `input.xlsx` — your input file *(web interface only)*
- `settings.json` — a structured text file containing the settings
   with which the web interface was run

[comment]: # (pandoc README.md -f gfm -V geometry:a4paper -V geometry:margin=2cm -V fontsize=12pt -V mainfont="Myriad Pro Light" --pdf-engine=xelatex -o README.pdf)
