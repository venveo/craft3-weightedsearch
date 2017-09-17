# Weighted Search plugin for Craft CMS 3.x

A remake of the [weighted search plugin](https://github.com/wja-no/craft-weighted-search) for Craft 2 by @wja-no


## Usage
This plugin exposes the template variable/function: `craft.weightedSearch.substringSearch(query, [sections])`

For more complete documentation, take a look at that repository.

This works exactly like the Craft 2 version of this plugin, where search results are weighted based on exact match and number of matches.

The return results of the search differs from the traditional Craft search query in that it returns some extra meta information about the search results.

Each result has these fields: `entry`, `excerpt` and `score`. The excerpt is in HTML format, where each instance of the search string has been marked up with the mark element.

You can leverage entry as you would any other ElementCriteriaModel (eg. `{{searchresult.entry.title}}`).

### Editorially prioritizing an entry for a search term
To enable manual prioritization of entries, create a field of type Tags, give it the handle prioritizedSearchTerms and add it to the relevant entry types.

To give an entry prioritization in the search results for a given term, add that term as a tag in the entry's prioritizedSearchTerms field. The entry will receive a significant boost to its score, which will most likely be enough to "win" over any other entry (that doesn't also have the same tag).
## Requirements

This plugin requires Craft CMS 3.0.0-beta.23 or later.

## Installation

1. Then tell Composer to load the plugin:

        composer require venveo/craft3-weightedsearch

2. In the Control Panel, go to Settings → Plugins and click the “Install” button for Better Search.