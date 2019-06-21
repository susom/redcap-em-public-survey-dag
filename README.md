# redcap-em-public-survey-dag
Assign public survey results to a specific DAG

## Global Setup

1. Enable the EM on your Control Center (only once).  
If your server uses shibboleth, you probably want to set the global option to Enable API Urls.


## Project Setup

1. Enable the EM on your project
1. A new side-panel link should appear called "Public Survey DAG Urls" - click here to see the new urls you should
distribute for use.
1. You can optionally have new records prefixed with the unique dag name so instead of sharing numbers from a common
pool, each DAG has its own ID counter.


## Assumptions

1. This was built for a single-arm project.  If you want to extend to multi-arms you'll have to add that functionality
1. It assumes the first survey is on the first event.  Basically, if you don't have a public url for your project this
will not work.
1. It creates new records based on numerical numbering.


## How it works

The DAG ID is stored in an obfuscated parameter in the url.  When someone arrives at the EM url, it decodes the DAG
ID and then creates the next record id and adds it to the DAG.  After this, it pulls the survey url for the first
form in the project and redirects.

