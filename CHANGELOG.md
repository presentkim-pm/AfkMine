# Changelog

## 1.0.0 (2026-11-27)

The Poggit plugin version for the original [pmmp/PocketMine-MP](https://github.com/pmmp/PocketMine-MP) 5.x is kept on the
[`legacy/pmmp-5.44`](https://github.com/presentkim-pm/AfkMine/tree/legacy/pmmp-5.44) branch.

### ⚠️ Breaking changes
- Targets [axolotl-pm/PocketMine-MP](https://github.com/axolotl-pm/PocketMine-MP) `5.51` (BedrockProtocol 64,
  Bedrock `1.26.60`) and **PHP 8.4+**.
- Requires the [CameraAPI](https://github.com/presentkim-pm/CameraAPI) `2.0` plugin.
- Distributed as a phar from GitHub Releases instead of Poggit. The libraries (`session-utils` 2.0,
  `selection-visualize-utils` 2.0, `play-sound-utils` 4.0, `libmultilingual` 2.0) are installed with Composer and
  bundled into the phar.
- The `player-state-backup` virion is no longer used (it is not published as a Composer package): the creation mode
  backs up and restores the operator's inventory by itself.
- Sources moved from `src/kim/present/afkmine/` to `src/` (`src-namespace-prefix` in `plugin.yml`); class names are unchanged.

### 🔄 Changes
- Sessions are closed by `session-utils` when the plugin is disabled, so `onDisable()` was removed.
- The miner's equipment packet uses the player's own network session `TypeConverter`.
- `composer.json` and PHPUnit tests (mine data, ore presets, config, utilities, class loading) added.

### 🐛 Fixes
- A mine whose first camera point had been removed during creation was saved with a gap in the camera points and failed
  to start (`cameraPoints[0]` did not exist). Camera points are now saved and loaded as a gap-free list.
- A `regenInterval` of `0` in `mines.json` crashed the mine tick with a division by zero; it is now at least `1`.
- The random camera offset of the loop timeline was computed once when the timeline was built; it is now re-rolled each
  time a camera point is played and follows the miner's current position.
- With exactly two camera points the loop played every point twice per cycle.
