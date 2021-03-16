# Data objects and database tables

Each data table in the database has a corresponding class that is
used to access the database data in a structured way.

Generic functionality for instantiating these classes and storing
data back to the tables is provided by the base class `comcal_Database_Table`. Besides
the numeric identfier `id` for each table row, entries have a unique identifier
that is mainly used to identify a certain object (e.g., `event:1234abcd`, `category:caffee00`).
This approach allows to potentially store multiple revisions of the same object in
the database (e.g., to allow to undo changes that have been made to an event).
However, this feature is not actively used yet!

## Database format

The tables are created with the common Wordpress prefix of the current instance (`$wpdb->prefix`).

| Table | Class | |
| --- | --- | --- |
| prefix_comcal | comcal_Event | stores events |
| prefix_comcal_cats | comcal_Category | stores categories |
| prefix_comcal_evt_vs_cats | comcal_EventVsCategory | 'joining table' to connect events and categories |
