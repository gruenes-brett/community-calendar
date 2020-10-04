# Community Calendar Wordpress Plugin

The purpose of this plugin is to provide a straight-forward way of
displaying a calendar with public events. Users may submit events and have
them displayed in the calendar (after they have been revised and activated by
a page admin).

A main goal for this plugin is to be simple in configuration, simple to use,
and to have a clean and tidy presentation of the events.


## Installation

1. Download or clone all the files from this repository into the plugins directory of your Wordpress
site (usually `wp-content/plugins/`). The directory must be named `community-calendar` (`git clone` will
create this directory for you).
```
cd <root_of_page>/wp-content/plugins
git clone https://github.com/joergrs/community-calendar.git
```
2. Activate the plugin in the the admin area of your page

## Usage

### Integration into the Wordpress page

The plugin introduces its functionality using short codes. Differently named calendars may
be shown on different pages, but only one calendar may be shown on a single page (as of right now).

**Show the calendar, starting today:**

  `[community-calendar-table start=today days=30 name=Main style=table]`

Attributes:
  * `start=today` hides past events. More options:\
    `start=next-monday`: show events starting next monday\
    `start=2020-10-22`: show events starting at 22nd October 2020\
    If omitted, all events that are in the database are shown.
  * `days=30` shows events from the next 30 days after start date.\
    Only works if `start` is set.
  * `name=CalendarName` specifies a name for this calendar.\
    Only events are shown that are added to this calendar. This allows to show
    different calendars on different pages.
  * `style=table|markdown`: Show the events as table (default) or as a markdown
    overview that can be copy/pasted to a Telegram chat or group.

**Display floating buttons that allow the user to submit an event and to scroll
 back to the current day**

 `[community-calendar-buttons]`

If logged in, an additional button is shown that can be used to organize categories.

### Workflow for submitting and editing events

Non-logged-in users:

* May submit an event using the `+` button
* Event will not be shown until an authorized user revises it and flags it as 'public'

Logged-in users:

* May edit events by clicking the events' `edit` button
* May set events 'public' or hide them again.


## Customization

In your CSS you may change the behavior and visual style of the elements.
Here are some examples:

### Background color of the event detail display and the event form
```css
.comcal-modal-wrapper {
    background-color: #222;
    color: #ccc;
}
```

### Floating button color
```css
.comcal-floating-button-container button {
    background-color: #337;
    color: white;
}
```

## Upgrading

**Important note**

If the plugin is upgraded to a new version that alters the database format,
in order for this to take effect, the plugin should be *deactivated* and
immediately *activated* again.


## Contributing

If you encounter problems with the plugin or have ideas for improvements and
useful features, please open an issue.

If you even would like to contribute to the code, feel free to contact us at
uk-dd@posteo.de.