Domain "Database"
=================
Under this directory you should have a "domains" directory containing
a heirarchy of directories and files used to resolve names.  You can also have
an "extra" directory with the same structure.  Use the "extra" directory to add
additional domains not taken from third party repositories.

Domains are processed in domain resolution order, last-to-first, with each
subdomain represented by a directory.  The final subdomain should be a file
with the extension "txt" containing the name of the University, UTF-8 encoded.

As an example, the email addresses:

   jsmith@faculty.scripps.college.edu
   pjones@students.stevens.edu.au

would map to the directory structure below:

    domains
       |
       +-- edu
       |    |
       |    +-- college
       |           |
       |           +-- scripps
       |                  |
       |                  +-- faculty.txt
       +-- au
           |
           +-- edu
                |
                +-- stevens
                       |
                       +-- students.txt

This format exactly matches the format used by the JetBrains database available
at https://github.com/JetBrains/swot.
