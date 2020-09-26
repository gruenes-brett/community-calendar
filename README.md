# Events Calendar Wordpress Plugin

The purpose of this plugin is to provide a straight-forward way of
displaying a calendar with public events. Users may submit events and have
them displayed in the calendar (after they have been revised and activated by
a page admin).


## Installation

1. Download or clone all the files from this repository into the plugins directory of your Wordpress
site (usually `wp-content/plugins/`). The directory must be named `events-calendar` (`git clone` will
create this directory for you).
```
cd <root_of_page>/wp-content/plugins
git clone https://github.com/joergrs/events-calendar.git
```
2. Activate the plugin in the the admin area of your page

## Usage

The plugin introduces its functionality using short codes:

* Show the calendar, starting today:

  `[events-calendar-table startToday=true/]`

  Use `startToday=false` to also display past events

* Display floating buttons that allow the user to submit an event and to scroll
 back to the current day

 `[events-calendar-buttons/]`
