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
