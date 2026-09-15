Events are individual performances/showtimes, usually tied to a [[productions|production]] (e.g. a specific Friday-night showing during a production's run), though a standalone event can exist without one. Recurring performances are created as separate Event rows at creation time rather than stored as a recurrence rule. Events surface publicly on the `/calendar` page.

## Meta Data
- **id** - the unique identifier used in the application
- **slug** - used for URL segments, auto-generated from the date and title/production name
- **production** - the [[productions|production]] this performance belongs to, if any (nullable)
- **startsAt** - the date/time the performance begins
- **endsAt** - the date/time the performance ends, if known (nullable)
- **status** - one of `scheduled`, `cancelled`, `postponed`, `rescheduled`
- **ticketUrl** / **effectiveTicketUrl** - this performance's own ticket link, falling back to the parent production's if unset
- **venue** / **effectiveVenue** - this performance's own [[venues|venue]], falling back to the parent production's if unset
- **title** - an optional override for the display name, otherwise the parent production's name is used
- **notes** - optional plain-text notes about the performance
