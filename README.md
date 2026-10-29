# Moodle Logstore xAPI

A Moodle log store that turns Moodle events into [xAPI](https://github.com/adlnet/xAPI-Spec/blob/master/xAPI.md) statements and sends them to a Learning Record Store (LRS).

**Get it from [CAMP](https://camp-registry.org/plugin/logstore_xapi)**, the Moodle community plugin registry, where every release is published first. It reaches the [Moodle Marketplace](https://marketplace.moodle.com/plugins/logstore_xapi) once Moodle has reviewed it.

- Supports Moodle 4.5 to 5.3, and is tested on PostgreSQL and MariaDB. Oracle isn't supported.
- Each release's changes are in [CHANGES.md](CHANGES.md).

## Documentation

- [Admin guide](docs/admin-guide.md): install, connect to the LRS, choose what is sent, handle failures, send past events
- [How it works](docs/how-it-works.md): the path from event to statement, the tasks and the tables
- [Develop the plugin](docs/developing.md): change a statement, support a new event, run the tests
- [Supported events](docs/supported-events.md)

Report bugs, suggest features and ask questions in the [issue tracker](https://github.com/davidpesce/moodle-logstore_xapi/issues).
