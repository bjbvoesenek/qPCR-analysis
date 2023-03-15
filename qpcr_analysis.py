# -*- coding: utf-8 -*-
"""
Script for automating the analysis of qPCR data

"""

#%% Provide the following parameters
housekeeping_gene1 = 'GAPDH'
housekeeping_gene2 = 'Bactin'


#%% Get working directory
import os
os.chdir('I:/lab-j/Bas/qPCR script')
working_dir = os.getcwd()


#%% sort and plot

import pandas as pd
import sys
# Check if analysis excel file exist
if not os.path.exists(os.path.join(working_dir, 'Analysis.xlsx')):
    sys.exit('Analysis.xlsx not found. Make sure the file is named correctly.')
   
# Check if the right sheet exist
from openpyxl import load_workbook
user_wb = load_workbook('Analysis.xlsx', read_only = True)

if 'Removed empty wells' in user_wb.sheetnames:
    data = pd.read_excel('Analysis.xlsx', sheet_name='Removed empty wells', engine='openpyxl')
else:
    sys.exit('Sheet [Removed empty wells] not found in Analysis.xlsx. Make sure the sheets in your excel workbook are named correctly')

import matplotlib.pyplot as plt
import math
from natsort import natsorted, index_natsorted, order_by_index

if 'input.txt' in data: del data['input.txt']

sample_names = data['Text']
index = index_natsorted(sample_names)
sample_names_df = sample_names.to_frame()

# Find unique primers and samples
primer_names = sample_names_df.copy()
primer_names = primer_names.iloc[:,0]
cell_lines = sample_names_df.copy()
cell_lines = cell_lines.iloc[:,0]

for n in range(0,len(primer_names)):
    primer_names[n] = primer_names[n].partition('_')[2]
    cell_lines[n] = cell_lines[n].partition('_')[0]
unique_primers = primer_names.unique()
unique_cell_lines = cell_lines.unique()

x = range(0, data.shape[1] - 1)

size_subplots = data.shape[0]
size_subplots = math.sqrt(size_subplots)
size_subplots = math.ceil(size_subplots)

plt.figure(figsize = (300,40))
plt.subplots_adjust(hspace = 0.5, wspace = 0.3)
#plt.suptitle('Melting curves', fontsize = 100)

nr_primersets = len(unique_primers)
nr_samples = len(unique_cell_lines)
nr_replicates = len(sample_names) / nr_samples / nr_primersets

rows = nr_samples
columns = nr_primersets * nr_replicates

subplot_number = 1
name_counter = 0

max_yvalue = data.max(numeric_only=True)
max_yvalue = max_yvalue.max() * 1.05

df_for_plotting = data
if 'Text' in df_for_plotting: del df_for_plotting['Text']

for i in range(len(index)):
    ax = plt.subplot(rows, columns, subplot_number)
    subplot_number = subplot_number + 1
    y = df_for_plotting.loc[index[i]]
    plt.plot(x,y)
    plt.ylim(0,max_yvalue)
    ax.set_title(sample_names[index[i]], fontsize = 30)
    name_counter = name_counter + 1
    
plt.savefig(os.path.join(working_dir, 'qPCR_plots_sorted.pdf'), bbox_inches='tight')

#%% Plot melting curves

if 'Melting curves' in user_wb.sheetnames:
    df_melting = pd.read_excel('Analysis.xlsx', sheet_name='Melting curves', engine='openpyxl')

    for col in df_melting.columns:
        if col.startswith('Unnamed'):
            del df_melting[col]
        
    x_melting = df_melting[df_melting.columns[index[0]]]
        
    for col in df_melting.columns:
        if col.startswith('X'):
            del df_melting[col]

    plt.figure(figsize = (300,40))
    plt.subplots_adjust(hspace = 0.5, wspace = 0.3)
    #plt.suptitle('Melting curves', fontsize = 100)

    subplot_number = 1
    name_counter = 0
    index_number = 0
    
    y_max = max(df_melting.max()) * 1.1

    for i in range(len(index)):
        ax = plt.subplot(rows, columns, subplot_number)
        subplot_number = subplot_number + 1
        y = df_melting[df_melting.columns[index[i]]]
        plt.plot(x_melting,y)
        plt.ylim(-0.5,y_max)
        ax.set_title(sample_names[index[i]], fontsize = 30)
        name_counter = name_counter + 1
        index_number += 1

    plt.savefig(os.path.join(working_dir, 'Melting curves sorted.pdf'), bbox_inches='tight')
else:
    print('Sheet [Melting curves] not found in Analysis.xlsx. Continuing to the next step...')

#%% calculate average Ct per condition

if 'Removed empty wells_compact' not in user_wb.sheetnames:
    sys.exit('Sheet [Removed empty wells_compact] not found in Analysis.xlsx. Make sure the sheets in your excel workbook are named correctly')
    
df_compact = pd.read_excel('Analysis.xlsx', sheet_name='Removed empty wells_compact', engine='openpyxl')

df_Ct = df_compact.iloc[3:3+len(sample_names), 4]
df_Ct = df_Ct.to_frame()
df_Ct = df_Ct.reset_index()
df_Ct['Sample'] = sample_names
del df_Ct['index']
df_Ct = df_Ct.rename(columns={'Chemistry: DNA binding dye (non-saturating' : 'Ct value', 'Sample' : 'Sample'})
df_Ct['Ct value'] = df_Ct['Ct value'].astype(float)

Ct_grouped = df_Ct.groupby(['Sample']).mean()

Ct_rownames = Ct_grouped.index
Ct_rownames = Ct_rownames.tolist()

for i in range(0,len(Ct_rownames)):
    Ct_rownames[i] = Ct_rownames[i].replace("input_txt_", "")

Ct_grouped.index = Ct_rownames
Ct_grouped['Index'] = Ct_rownames

# SAVE AVERAGE CT VALUES
Ct_grouped.to_excel("Avg_Ct_values.xlsx")  


#%% Plot Bargraph

# Determine size subplots
size_subplots = len(unique_primers)
size_subplots = math.sqrt(size_subplots)
size_subplots = math.ceil(size_subplots)

subplot_number = 1

color_list = [''] * nr_samples
avg_Ct_df = pd.DataFrame()

# Initialize figure (increase figsize if plots don't fit)
plt.figure(figsize = (20,20))
plt.subplots_adjust(hspace = 0.5, wspace = 0.3)

# Loop over unique primers and plot Ct values as bar graph
for i in range(0,len(unique_primers)):
    ax = plt.subplot(size_subplots, size_subplots, subplot_number)
    subplot_number += 1
        
    temp_df = Ct_grouped[Ct_grouped['Index'].str.contains(unique_primers[i])]
    temp_values = temp_df['Ct value'].tolist()
    temp_samples = temp_df.index
    temp_samples = temp_samples.tolist()
    for j in range(0,len(temp_samples)):
        temp_samples[j] = temp_samples[j].replace('_' + unique_primers[i], "")
        
    # Store Ct values per primer in seperate dataframe (later save to excel)
    avg_Ct_df[unique_primers[i]] = temp_values 
         
    for k in range(0,len(temp_samples)):
        if temp_samples[k].startswith('Control'):
            color_list[k] = 'gray'
        if temp_samples[k].startswith('FLB'):
            color_list[k] = 'gray'
        if temp_samples[k].startswith('APP'):
            color_list[k] = 'blue'
        if temp_samples[k].startswith('H2O'):
            color_list[k] = 'green'
        else:
            color_list[k] = 'gray'
    
    plt.bar(temp_samples, temp_values, color=color_list, alpha=0.7)
    plt.xticks(rotation='vertical')
    plt.ylim(0,40)
    plt.title(unique_primers[i])

plt.savefig(os.path.join(working_dir, 'Average_Ct_bargraph.pdf'), bbox_inches='tight')

avg_Ct_df.index = temp_samples
#avg_Ct_df.to_excel("Avg_Ct_values.xlsx")  


#%% Calculate relative expression

from statistics import mean

# Initialize dataframe and check if housekeeping genes are in the dataframe
dCt_df = pd.DataFrame()

if not housekeeping_gene1 in avg_Ct_df.columns and housekeeping_gene2 in avg_Ct_df.columns:
    sys.exit('The provided housekeeping genes could not be found in the data. Check if housekeeping genes are named correctly.')

# Subtract housekeeping gene Ct from primer Ct in every row/sample (delta Ct)
for index, row in avg_Ct_df.iterrows():#dCt_df.iterrows():
    for i in range(0,len(unique_primers)):
        avg_Ct_housekeeping = mean([avg_Ct_df.loc[index, housekeeping_gene1], avg_Ct_df.loc[index, housekeeping_gene2]])
        dCt_df.loc[index, unique_primers[i]] = avg_Ct_df.loc[index, unique_primers[i]] - avg_Ct_housekeeping
        
# Average control lines to calculate ddCt
control = [i for i in dCt_df.index if i.startswith('FLB') or i.startswith('Control')]
dCt_df.loc['Control average'] = dCt_df.loc[control,:].mean()

# subtract dCt of gene of interest from dCt of control average
ddCt_df = pd.DataFrame()

for i in range(0,len(unique_primers)):
    for index, row in dCt_df.iterrows():
        #ddCt_df.loc[index, unique_primers[i]] = dCt_df.loc['Control average', unique_primers[i]] - dCt_df.loc[index, unique_primers[i]] 
        ddCt_df.loc[index, unique_primers[i]] = dCt_df.loc[index, unique_primers[i]] - dCt_df.loc['Control average', unique_primers[i]]

# Calculate relative ddCt (2^-2ddCt)
rel_ddCt_df = pd.DataFrame()

for index, row in ddCt_df.iterrows():
    for i in range(0,len(unique_primers)):
        rel_ddCt_df.loc[index, unique_primers[i]] = 2 ** -(ddCt_df.loc[index, unique_primers[i]])

# Save relative ddCt values to excel
rel_ddCt_df.to_excel("Relative ddCq.xlsx")

# Remove H2O sample before plotting
rel_ddCt_df = rel_ddCt_df.drop('H2O')

## Plot relative ddCt values
color_list = [''] * rel_ddCt_df.shape[0]
subplot_number = 1
plt.figure(figsize = (20,20))
plt.subplots_adjust(hspace = 0.5, wspace = 0.3)
y_max = max(rel_ddCt_df.max()) * 1.1

# Loop over unique primers and plot Ct values as bar graph
for i in range(0,len(unique_primers)):
    ax = plt.subplot(size_subplots, size_subplots, subplot_number)
    subplot_number += 1
        
    temp_values = rel_ddCt_df[unique_primers[i]].tolist()
    temp_samples = rel_ddCt_df.index
    temp_samples = temp_samples.tolist()
    
    for j in range(0,len(temp_samples)):
        temp_samples[j] = temp_samples[j].replace('_' + unique_primers[i], "")
    
    for k in range(0,len(temp_samples)):
        if temp_samples[k].startswith('Control'):
            color_list[k] = 'gray'
        if temp_samples[k].startswith('FLB'):
            color_list[k] = 'gray'
        if temp_samples[k].startswith('APP'):
            color_list[k] = 'royalblue'
        else:
            color_list[k] = 'gray'
    
    plt.bar(temp_samples, temp_values, color=color_list, alpha=0.8)
    plt.xticks(rotation='vertical')
    plt.ylim(0,y_max)
    plt.title(unique_primers[i])

plt.savefig(os.path.join(working_dir, 'Relative_Ct_bargraph.pdf'), bbox_inches='tight')












