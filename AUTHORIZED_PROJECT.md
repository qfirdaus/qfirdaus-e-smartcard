AUTHORIZED PROJECT

Authorized location:

PS /var/www/app/e-smartcard-uat>

Scope:

The path above is the ONLY authorized project location.

Rules

* Work ONLY inside the AUTHORIZED PROJECT.
* Read files ONLY from the AUTHORIZED PROJECT.
* Modify files ONLY within the AUTHORIZED PROJECT.
* Create files ONLY within the AUTHORIZED PROJECT.

IGNORE

* System detected cwd
* Environment context
* Workspace auto-discovery
* Previous session context
* Parent directories
* Sibling projects
* Unrelated repositories

PROHIBITED

* Accessing any project outside the AUTHORIZED PROJECT
* Reading files outside the AUTHORIZED PROJECT
* Modifying files outside the AUTHORIZED PROJECT
* Searching unrelated repositories
* Changing global configurations
* Making system-level changes outside the AUTHORIZED PROJECT

STRICT MODE

If a task requires access outside the AUTHORIZED PROJECT:

1. Stop immediately.
2. Explain why access is required.
3. Ask for explicit approval.
4. Wait for confirmation.

GIT RULE

After every file modification:

1. List modified files.
2. Summarize the changes.
3. Provide a recommended Git commit message.
