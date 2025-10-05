# CakePHP Rhythm Plugin

The **Rhythm** plugin provides comprehensive real-time performance monitoring for CakePHP applications with the following features:

Rhythm shows you how your CakePHP app is performing. You can find slow parts, see which users are most active, check server health, and more.

The plugin provides comprehensive metric collection including server performance monitoring (CPU, memory, disk usage), HTTP request tracking with response times and status codes, database query performance with slow query detection, queue system monitoring for job processing statistics, exception tracking and reporting, and outgoing request monitoring for external API calls.

Real-time dashboard with interactive widgets provides live performance monitoring, while flexible storage options support both database and Redis backends with automatic aggregation. The plugin includes configurable sampling rates for different metric types, automatic grouping of similar metrics to reduce cardinality, and multi-server support for unified monitoring across environments.

The plugin works with CakePHP's event system and includes command line tools for managing data and checking server health.

## Requirements

* PHP 8.2+

See [Versions.md](docs/Versions.md) for the supported CakePHP versions.

## Documentation

For documentation, as well as tutorials, see the [docs](docs/index.md) directory of this repository.

## License

Licensed under the [MIT](http://www.opensource.org/licenses/mit-license.php) License. Redistributions of the source code included in this repository must retain the copyright notice found in each file.
