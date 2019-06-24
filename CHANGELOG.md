# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## Unreleased

### Added

 - An `Akkroo\Client` object able to perform CRUD operations on the Akkroo API in a simple way.
 - The Client can be used with any PSR-7 HTTP client that must be injected on creation.
 - The Client can use an optional PSR-4 logger, injected with the `setLogger()` instance method.
 - Server responses are converted to PHP objects. A response can be a generic `Result`, a `Resource`
   (i.e. Company, Event, Record) or a `Collection` of resources.
 - Server side errors are converted to proper PHP exception types (Generic, Authentication,
   Validation, NotFound).
 - Each request has a unique `Request-ID` header, that can be overridden and matched to
   a server operation. This will allow to implement server-side idempotency on POST requests.
 - Initial support for Akkroo Public API v2.
