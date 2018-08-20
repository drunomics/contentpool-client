# Per-branch environment variables

Files named the same as the current branch will get sourced, such that
such that environment variables may be declared.

Slashes in branch names are replaced by "--", e.g. for "feature/test" a script 
"feature--test.sh" is executed.

Note: The file must be named "${BRANCH}.sh" *and* must be executable.

## Supported variables:

* Custom versions of the contentpool server can be used by setting the
  variable LAUNCH_CONTENTPOOL_GIT_BRANCH. 
* PRE_BUILD_COMMANDS may be set and are executed before the contentpool project
  is built.
