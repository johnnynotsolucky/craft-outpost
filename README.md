# Outpost

![](docs/header.png)

Outpost is a debug plugin for Craft CMS. It provides instruments to track and gain insights into requests, exceptions, events and logs.

## Requirements

- Craft CMS 3.x

## Installation

```bash
$ composer require johnnynotsolucky/outpost
$ ./craft install/plugin outpost
```

## Features

- Advanced request logging
- Request sampling
- Exception stacktraces
- Profiling
- Configurable

## Configuration

- **Include Control Panel Requests** - Track Craft Control Panel requests.
- **Purge Limit** - The amount of requests to store in the database. Setting to zero disables purging older requests.
- **Request Sampling** - Only track roughly 5% of incoming requests. Uses whichever cache provider has been configured for Craft. Sampling occurs per unique URL, request method and response code. *Request sampling should be enabled if you intend to use this plugin on a production site.*
- **Minimum Log Level** - Minimum log level to store for all requests.
- **Minimum Log Level** - When an exception is tracked, increase the log outpot.
- **View Grouped Items** - When available, group similar tracked items, e.g. Exceptions by exception class.

## Purging

In Outpost settings, all tracked requests and related data can be cleared with the "Clear Requests" button.
