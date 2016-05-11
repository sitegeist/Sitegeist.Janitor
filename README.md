# Sitegeist.Janitor

> Clean up your content repository

## Installation

Add this to your composer.json:

```
{
  "repositories": [
    {
        "url": "ssh://git@git.sitegeist.de:40022/sitegeist/Sitegeist.Janitor.git",
        "type": "vcs"
    }
  ]
}
```
Now you can require Sitegeist.Janitor with composer:

```
composer require sitegeist/janitor
```

## Usage

Sitegeist.Janitor consists of a couple of flow commands to help you inspect your content repository and discover
optimization potential.

### report:unused

Find out, what NodeTypes are not used inside your Neos instance.

```shell
./flow report:unused
```

**Options:**
* `--threshold` (optional, default: 0) - You can use this parameter to detect more than just unused NodeTypes, but basically all NodeTypes that have lesser occurences than this threshold
* `--super-type` (optional, default: 'TYPO3.Neos:Node') - With this parameter, you can limit the set of considered NodeTypes to those that inherit a specific SuperType
* `--workspaces` (optional, default: 'live') - You can also limit the set of considered workspaces

### report:occurences

Get a list of all occurences of a specific node type.

```shell
./flow report:occurences MyAwesome.Package:MyAwesomeNodeType
```

**Options:**
* `--node-type` (required) - The node to which you want to find the occurences
* `--workspaces` (optional, default: '\_all') - Limit the set of considered workspaces
* `--limit` (optional, default: 5) - With this parameter, you can limit the number of occurences that are listed in the report
* `--start-at` (optional, default: 1) - Specifies the index of the result at which to start the report

### report:nodetypes

Get an overview of all your node types.

```shell
./flow report:nodetypes
```

**Options:**
* `--super-type` (optional, default: 'TYPO3.Neos:Node') - With this parameter, you can limit the set of considered NodeTypes to those that inherit a specific SuperType
* `--filter` (optional, default: '') - Filter your results with a shell glob pattern, leave this empty to not filter at all
* `--abstract` (optional, default: false) - Consider abstract node types as well
* `--oneline` (optional, default: false) - Creates a condensed report

### report:whereallowed

Find out where a particular node type is allowed. This command gives you a list of NodeTypes and auto created child nodes and highlights those in which the given node type is allowed.

```shell
./flow report:whereallowed TYPO3.Neos:Content
```

**Options:**
* `--node-type` (required) - The node type to analyze
* `--filter` (optional, default: '') - Filter your results with a shell glob pattern, leave this empty to not filter at all

### report:uris

Get a list of all Uris for a given node type.

```shell
./flow report:uris
```

**Options:**
* `--node-type` (optional, default: 'TYPO3.Neos:Document') - The node type to analyze
* `--filter` (optional, default: '') - Filter your results with a shell glob pattern, leave this empty to not filter at all
* `--workspace` (optional, default: 'live') - Limit your results to a certain workspace
* `--verbose` (optional, default: false) - Increase verbosity
* `--limit` (optional, default: 0) - Limit the number of your results (0 = no limit)

## License

See [LICENSE.md](./LICENSE.md)
