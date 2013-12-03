## 1.1.0

* [Improvement] 'query' option in /run request to run a specific query only.
* [Improvement] Log runId in all events.
* [Improvement] Added logging to StorageApi Evennts
* [Improvement] If a query returns no results, create an empty table.

## 1.0.0

* [Bugfix] overwriting 'Access-Control-Allow-Origin' header instead of appending

## 2013-11-22

* [Feature] Added DELETE method for /configs
* [Improvement] /account remapped to /configs, all "account" attributes in API calls can be also "config"
* [Improvement] Added config name to OAuth token refresh exception message
* Salesforce extractor is now fully compliant with https://github.com/keboola/api-guidelines