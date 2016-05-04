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

## License

See [LICENSE.md](./LICENSE.md)
