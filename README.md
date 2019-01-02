# XMLParserReader
Parse from any source to and reads from XML files.

This project provides an interface that can be used for mocking databases and/or single tables.

# Parsing a Database
Given a database object to access and retrieve data from a source (all rows retrieved must be in array format, with the table's columns' names as the array keys) 
you can use the ParseXML file to convert the source's tables into XML files.

# Usage
The ValueObject file gives an example of how the mocked tables' rows can be converted into useful objects for use in PHP.
Each table must be instantiated with a different XMLInterface class.

# Notes
It's important to note that this project isn't aimed to perform as good as conventional databases, thus its processing time grows hand in hand with the amount of rows in a table.

