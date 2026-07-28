# AiAsyncProcessor Changelog

## [1.0.0] — 2026-07-28

### Added
- Initial release: `WebtechSolutions_AiAsyncProcessor` module
- Generic asynchronous task processing infrastructure for AI-related operations, built on RabbitMQ/AMQP, so slow external AI calls no longer block customer-facing requests
- Extensible task handler registry: new AI task types can be added by other modules without modifying this module
- Automatic retry on transient failures, with failed tasks routed to a dedicated error queue after repeated failures
- Structured logging of all task processing attempts and failures to a dedicated log channel
