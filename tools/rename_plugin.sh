#!/bin/bash

SCRIPT_DIR=$(dirname "$(readlink -f "$0")")
PARENT_FOLDER_PATH=$(dirname "$SCRIPT_DIR")
PLUGINNAME=$(basename "$PARENT_FOLDER_PATH")

if [ "$#" -gt 1 ]; then
    echo "Usage: $0 [\"Display name\"]"
    echo
    echo "  The plugin key is taken from this directory ($PLUGINNAME), so it can"
    echo "  never disagree with the repository it was cloned from. To use a"
    echo "  different key, rename the directory."
    echo
    echo "  Display name  optional, shown in the plugin list. May contain spaces."
    echo "                Defaults to the capitalised key."
    echo
    echo "Examples:"
    echo "  $0"
    echo "  $0 \"My Plugin\""
    exit 1
fi

# The directory name is the single source of truth: GLPI derives the plugin key
# from it, and the repository was already named by whoever created it. Asking for
# it again would only allow the two to drift apart.
PLUGINNAME_MINUS=$PLUGINNAME

# Same rule GLPI applies to a plugin directory (Plugin::PLUGIN_KEY_PATTERN),
# minus the case-insensitive flag: uppercase would pass validation but the core
# compares the key lowercased in some paths and applies ucfirst() in others.
if [[ ! $PLUGINNAME_MINUS =~ ^[a-z0-9]+$ ]]; then
    echo "This directory cannot be used as a plugin key: $PLUGINNAME_MINUS"
    echo
    echo "GLPI only accepts lowercase letters and digits (no _ - or .). Rename the"
    echo "directory to the key you want and run this script again:"
    echo
    echo "  cd .. && mv \"$PLUGINNAME\" <plugin-key> && cd <plugin-key>"
    echo
    echo "If you cloned a repository whose name is not a valid key, clone it under"
    echo "the right one instead:  git clone <url> <plugin-key>"
    exit 1
fi

# Single capital, never camelCase: GLPI splits a class name on its capitals to
# build the table name, so ActualTime would yield glpi_plugin_actual_times.
PLUGINNAME_CAPIT=$(echo "$PLUGINNAME_MINUS" | sed 's/./\U&/')
PLUGINNAME_MAYUS=$(echo "$PLUGINNAME_MINUS" | tr '[:lower:]' '[:upper:]')
PLUGINNAME_PASCAL=${1:-$PLUGINNAME_CAPIT}

# It ends up inside single-quoted PHP strings ('name' => '...'), so a quote would
# break the file it lands in.
if [[ $PLUGINNAME_PASCAL == *"'"* || $PLUGINNAME_PASCAL == *'"'* ]]; then
    echo "The display name cannot contain quotes"
    exit 1
fi

STRING_PASCAL="0GLPIXO"
# Namespace segment, so it must be a valid PHP identifier: it cannot start with
# a digit like the other three placeholders do.
STRING_CAPIT="Zzglpixx"
STRING_MINUS="0GLPIxx"
STRING_MAYUS="0GLPIXX"

# Skipping .git is not cosmetic: a placeholder occurring inside a packfile would
# be rewritten by sed and corrupt the object. Binary files are skipped for the
# same reason (tools/phpstan/phpstan.phar).
replace_placeholder() {
    local search="$1"
    local replace="$2"

    # The display name is free text, so escape what sed reads as syntax in a
    # replacement: a backslash, the delimiter, and & (the whole match).
    replace=$(printf '%s' "$replace" | sed -e 's/[\/&\\]/\\&/g')

    grep -rl --binary-files=without-match \
        --exclude-dir=.git --exclude-dir=vendor --exclude-dir=node_modules \
        -e "$search" "$PARENT_FOLDER_PATH" |
        xargs -r -d '\n' sed -i "s/$search/$replace/g"
}

replace_placeholder "$STRING_PASCAL" "$PLUGINNAME_PASCAL"
replace_placeholder "$STRING_CAPIT" "$PLUGINNAME_CAPIT"
replace_placeholder "$STRING_MINUS" "$PLUGINNAME_MINUS"
replace_placeholder "$STRING_MAYUS" "$PLUGINNAME_MAYUS"

# rename setup_template.php to setup.php
if [ -f $PARENT_FOLDER_PATH/setup_template.php ]; then
    mv $PARENT_FOLDER_PATH/setup_template.php $PARENT_FOLDER_PATH/setup.php
fi

# rename github folder to hidden .github
if [ -d $PARENT_FOLDER_PATH/github ]; then
    mv $PARENT_FOLDER_PATH/github $PARENT_FOLDER_PATH/.github
fi

# The bootstrap guide only describes the un-renamed template, so it is stale the
# moment this script runs. CONTRIBUTING.md stays: it applies to every plugin.
if [ -f "$PARENT_FOLDER_PATH/GETTING_STARTED.md" ]; then
    rm -f "$PARENT_FOLDER_PATH/GETTING_STARTED.md"
    # Drop the README block pointing at it, or every generated plugin ships a
    # link to a file that no longer exists.
    sed -i '/bootstrap-note:start/,/bootstrap-note:end/d' "$PARENT_FOLDER_PATH/README.md"
fi

# execute perl modify_headers.pl to change headers
if [ -f $SCRIPT_DIR/modify_headers.pl ]; then
    perl $SCRIPT_DIR/modify_headers.pl
fi

# Report any placeholder that survived, so a missed one is not discovered later
# by a broken workflow or a class that fails to autoload.
LEFTOVERS=$(grep -rl --binary-files=without-match \
    --exclude-dir=.git --exclude-dir=vendor --exclude-dir=node_modules \
    -e "$STRING_PASCAL" -e "$STRING_CAPIT" -e "$STRING_MINUS" -e "$STRING_MAYUS" \
    "$PARENT_FOLDER_PATH")
if [ -n "$LEFTOVERS" ]; then
    echo "WARNING: placeholders still present in:"
    echo "$LEFTOVERS"
fi

echo
echo "Done. Plugin key: $PLUGINNAME_MINUS"
echo "  namespace   GlpiPlugin\\$PLUGINNAME_CAPIT\\  (src/)"
echo "  templates   @$PLUGINNAME_MINUS/..."
echo "  routes      /plugins/$PLUGINNAME_MINUS/..."
echo "  shown as    $PLUGINNAME_PASCAL"