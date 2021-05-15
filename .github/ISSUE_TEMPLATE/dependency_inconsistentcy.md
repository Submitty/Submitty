---
name: Dependency inconsistency
about: Used by CI to warn about dependency inconsistency
title: 'Dependency inconsistent'
labels: bug, dependencies
assignees: 'wusatosi'
---

This message is auto generated.

**Describe the bug**

We need to use the same version of the dependency across this project.

A test has found a dependency inconsistency at the branch `{{ env.branch }}`.

This needs to be resolved.

**Debug Message**
```
{{ env.message }}
```
