# Contribute to valet-plus
Want to help out valet-plus and the community? We would love to have your help! This document explains how to do so!

## Summary

* [Issues / Features](#issue-templates)
* [Pull Requests](#pull-requests)
* [Releases](#releases)
* [MacOS versions](#macos-versions)
* [Changelog](#changelog)

## Bug and Feature requests

Before submitting any issues or features to the valet-plus issue queue follow the checklists below. These will also automatically 
be added when you create a new ticket. Filling in the templates helps contributors save time. To preview these templates check: 
- [Bug Report Template](./.github/ISSUE_TEMPLATE/bug_report.md) 
- [Feature Request Template](./.github/ISSUE_TEMPLATE/feature_request.md) 
- [Pull Request Template](./.github/PULL_REQUEST_TEMPLATE/pull_request_template.md) 

> _NOTE:_ If you do not follow these templates your ticket may be closed without feedback!

### Issue Tagging
After a contributor checked your branch and confirmed the bug/feature it will be tagged with `PATCH`, `MINOR` or `MAJOR`.
This will indicate in what kind of release the PR for this ticket should be merged. Read more about this below.

## Pull Requests
The valet-plus team is always happy to spend some time on reviewing your pull requests.
However to make the process easier and more fluid please follow the pull request template format.
Once your PR is submitted the continuous integration with Azure pipelines will start running and will check your request
for PSR-2 guidelines and compatibility with other installed features. 

### PR Tagging
PR's will get the same tag as their issue created in the the issue queue. This will allow for easy merging when new releases are created.
  
### What branch to target?
  
Please use the following overview to determine what branch you need to target.  

Kind of modification | Backward Compatible (BC) | Type of release | Branch to target        
-------------------- | ------------------------ | --------------- | -----------------------
Bug fix              | Yes                      | Patch           | `2.x`                  | 
Bug fix              | No (Only if no choice)   | Major           | `master`               | 
Feature              | Yes                      | Minor           | `2.x`                  | 
Feature              | No (Only if no choice)   | Major           | `master`               | 
Deprecation          | Yes (Have to)            | Minor           | `2.x`                  | 
Deprecation removal  | No (Can't be)            | Major           | `master`               | 


##  Releases

Not every contributor can make releases. The core valet-plus team will prepare releases when enough work in in queue to 
warrant a release. When creating a release the valet-plus team follows the following workflow:

- Prepare list of PR's to be merged. Any `MAJOR` or `MINOR` tags? Create a `MAJOR` or `MINOR` release else `PATCH`.
- Create a release branch `release/x.x.x` from the branch that requires a release. E.G: `2.x` or `master`.
- Update the version within `valet-plus/cli/valet.php` to match the release branch version.
- Merge PR's ready for release from PR queue.
- Update the CHANGELOG.md with changelog lines from the PR. 
- Update the CHANGELOG.md changes footer.
- Merge release branch to active branch (E.G: `2.x` or `master`) to increment the version number.
- Publish tag from release branch with the branch version as tag version in the format `vx.x.x`. E.G: `v2.0.1`, `v2.1.0`, etc..

### Major releases
Major releases should be done regularly to ensure the branches don't diverge too much. After a MAJOR release the branch 
structure changes: 

- the master branch becomes 4.x (The next major).
- 3.x becomes the stable branch (Current stable).
- 2.x becomes the legacy branch (Legacy, only very specific bugfixes/security updates).
- 1.x is abandoned (Totally abandoned, no support).

### Ensure branches don't diverge
To prevent branches diverging too much in commits before every release:

- Merge the legacy branch into the stable branch.
- Merge the stable branch into the unstable branch.

## MacOS versions
Officially the valet-plus teams supports the MacOS versions that are checked by Azure pipelines. However valet-plus
should work on unsupported MacOS versions. You may however run in some configuration issues or missing libraries. The
valet-plus team does not provide support for unsupported OS versions so be sure to upgrade to a supported MacOS version
when submitting a bug request.

Current supported MacOS versions:
- 'macOS-10.14' (Mojave)
- 'macOS-10.13' (High Sierra)

## Changelog
Every project needs a [changelog](./CHANGELOG.md) which easily shows what has changed in comparison to the previous release.
As of valet-plus version 2.x valet-plus will be tracking changes in CHANGELOG.md.


