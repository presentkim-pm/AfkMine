<!-- PROJECT BADGES -->
<div align="center">

![Version][version-badge]
[![Stars][stars-badge]][stars-url]
[![License][license-badge]][license-url]

</div>


<!-- PROJECT LOGO -->
<br />
<div align="center">
  <img src="https://raw.githubusercontent.com/presentkim-pm/AfkMine/main/assets/icon.png" alt="Logo" width="80" height="80">
  <h3>AFKMine</h3>
  <p align="center">
    Immersive AFK mining with a cinematic camera experience.

[Korean README](README_KOR.md) · [Report a bug][issues-url] · [Request a feature][issues-url]

  </p>
</div>


<!-- ABOUT THE PROJECT -->

## About The Project

This plugin is a PoC made to test the CameraAPI plugin and the `selection-visualize-utils`, `play-sound-utils` virions.

:heavy_check_mark: Create AFK mines in-game  
:heavy_check_mark: Watch a dummy miner mining ores via a cinematic camera path  
:heavy_check_mark: Receive real drops as rewards over time  
:heavy_check_mark: Supports multilingual messages and localized default config via `libmultilingual`  
:heavy_check_mark: Restores original blocks when deleting a mine  

##

-----

#### Requirements

- PocketMine-MP **5.x**
- PHP **8.2**

##

-----

#### Dependencies

- **CameraAPI** (plugin dependency)
- Virions used by this plugin:
  - `selection-visualize-utils`
  - `play-sound-utils`
  - `libmultilingual`

##

-----

#### Commands & Permissions

- `/afkmineadmin` (permission: `afkmine.command.admin`, default: OP)
  - `create` : enter mine creation mode
  - `list` : list registered mines
  - `delete <name>` : delete a mine by name (restores original blocks for its ore spots)
- `/afkmine` (permission: `afkmine.command.user`, default: true)
  - join/leave an available AFK mine session

##

-----

#### Usage

##### Create a mine (`/afkmineadmin create`)


https://github.com/user-attachments/assets/a9c53a70-d87a-40ec-95f9-35a549908171


1. Your inventory will be replaced with creation tools.
2. Touch blocks / use items to set positions:
   - Ore spot tools (slot 1–3): add/remove ore spots by preset (Stone/Nether/Deepslate)
   - Dummy spawn position
   - Camera position (look-at) (can be added multiple times)
   - Player hide position
   - Save and exit
3. In the save form, set:
   - mine name
   - ore regen interval (ticks)

##### Join AFK mining (`/afkmine`)


https://github.com/user-attachments/assets/45000b04-ac09-4758-82d7-6aa991598f5a


- Start/stop an AFK session in an available mine.
- Rewards are settled every `reward-interval` seconds.

##

-----

## Ore presets

Default presets are registered in `OrePresetRegistry`:

- IDs: `stone`, `nether`, `deepslate`
- Names are localized via libmultilingual keys:
  - `afkmine.orePreset.stone`
  - `afkmine.orePreset.nether`
  - `afkmine.orePreset.deepslate`

You can override presets by registering a new `OrePreset` with the same ID.

##

-----

## Multilingual support

### Messages

- Resource locale files:
  - `resources/locale/eng.ini` (required)
  - `resources/locale/kor.ini`
- Server owners can edit messages in:
  - `plugin_data/AFKMine/locale/*.ini`

### Localized default config

- Resource configs:
  - `resources/config/eng.yml` (required)
  - `resources/config/kor.yml`
- On first run (or if `plugin_data/AFKMine/config.yml` is missing), the plugin saves the server-language matching
  config to:
  - `plugin_data/AFKMine/config.yml`

##

-----

## World restore on mine delete

When you mark blocks as ore spots during creation, AFKMine stores the original block state IDs.
When the mine is deleted (`/afkmineadmin delete <name>`), it restores those blocks so the world
returns to its previous state instead of leaving regenerated ores/base blocks behind.

##

-----

## Installation

1) Download plugin `.phar` releases (or install from source for PoC testing)
2) Move downloaded `.phar` file to server's **/plugins/** folder
3) Restart the server

##

-----

## License

See the repository license file for more information.

##

-----

[version-badge]: https://img.shields.io/github/v/release/presentkim-pm/AfkMine?display_name=tag&style=for-the-badge&label=VERSION
[stars-badge]: https://img.shields.io/github/stars/presentkim-pm/AfkMine.svg?style=for-the-badge
[license-badge]: https://img.shields.io/github/license/presentkim-pm/AfkMine.svg?style=for-the-badge

[stars-url]: https://github.com/presentkim-pm/AfkMine/stargazers
[issues-url]: https://github.com/presentkim-pm/AfkMine/issues
[license-url]: https://github.com/presentkim-pm/AfkMine/blob/main/LICENSE

