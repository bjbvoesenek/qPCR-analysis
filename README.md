# qPCR-analysis

This script can be used after LinRegPCR analysis to plot your data in a clear way and automate the relative gene expression calculations. Data coming out of the analysis will consist of PDF files containing graphs and Excel files containing the data behind these graphs.

Before using this script, use the ‘Calculate baselines’ function in LinRegPCR. Remove rows with data from empty wells and name your samples as following: sample_primer (eg. Control1_GAPDH).

The Excel file provided to the script should be a .xlsx file. The sheet containing the data should be named ‘Data’ (LinRegPCR will create ‘Data_output’ and ‘Data_compact’). The sheet containing the data for the melting curves should be named ‘Melting curves’. The melting curves sheet is not necessary.
