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
    시네마틱 카메라 연출과 함께 즐기는 AFK 광산 플러그인.

[English README](README.md) · [버그 제보][issues-url] · [기능 요청][issues-url]

  </p>
</div>


<!-- ABOUT THE PROJECT -->

## About The Project

이 플러그인은 CameraAPI 플러그인 및 virion들의 테스트를 위해 만들어진 PoC 플러그인입니다.

:heavy_check_mark: 인게임에서 자동 광산 생성  
:heavy_check_mark: 카메라 경로를 통해 더미 광부의 채굴을 시네마틱하게 감상  
:heavy_check_mark: 실제 드롭 기반 보상 정산  
:heavy_check_mark: `libmultilingual`로 다국어 메시지 및 다국어 기본 config 지원  
:heavy_check_mark: 광산 삭제 시 광물 포인트 원본 블럭 복구  

##

-----

#### 요구사항

- PocketMine-MP **5.x**
- PHP **8.2**

##

-----

#### 의존성

- **CameraAPI** (플러그인 의존성)
- 이 플러그인이 사용하는 virion:
  - `session-utils`
  - `selection-visualize-utils`
  - `play-sound-utils`
  - `libmultilingual`
  - `player-state-backup`

##

-----

#### 명령어 및 권한

- `/afkmineadmin` (permission: `afkmine.command.admin`, 기본 OP)
  - `create` : 광산 생성 모드 진입
  - `list` : 등록된 광산 목록 확인
  - `delete <name>` : 해당 이름의 광산 삭제 (광물 포인트 원본 블럭 복구 포함)
- `/afkmine` (permission: `afkmine.command.user`, 기본 허용)
  - 사용 가능한 자동 광산 세션 입장/퇴장

##

-----

#### 사용법

##### 광산 생성 (`/afkmineadmin create`)


https://github.com/user-attachments/assets/a9c53a70-d87a-40ec-95f9-35a549908171


1. 명령어 실행 시 인벤토리가 생성 도구들로 교체됩니다.
2. 블럭을 터치하거나 아이템을 사용하여 위치를 지정합니다.
   - 광물 포인트(슬롯 1~3): 프리셋(Stone/Nether/Deepslate)별 광물 포인트 추가/제거
   - 더미 소환 위치
   - 카메라 위치(바라보는 곳) (여러 개 추가 가능)
   - 플레이어 은신 위치
   - 저장 및 종료
3. 저장 폼에서 다음 값을 설정합니다.
   - 광산 이름
   - 광물 리젠 주기(틱)

##### AFK 채굴 입장/퇴장 (`/afkmine`)


https://github.com/user-attachments/assets/45000b04-ac09-4758-82d7-6aa991598f5a


- 사용 가능한 광산에서 AFK 세션을 시작/종료합니다.
- 보상은 `reward-interval` 초마다 정산됩니다.

##

-----

## 광물 프리셋 (OrePreset)

기본 프리셋은 `OrePresetRegistry`에 등록되어 있습니다.

- ID: `stone`, `nether`, `deepslate`
- 이름은 libmultilingual 번역 키로 로컬라이즈됩니다.
  - `afkmine.orePreset.stone`
  - `afkmine.orePreset.nether`
  - `afkmine.orePreset.deepslate`

동일 ID로 `OrePreset`을 다시 등록하여 프리셋을 덮어쓸 수 있습니다.

##

-----

## 다국어 지원

### 메시지

- 리소스 로케일 파일:
  - `resources/locale/eng.ini` (필수)
  - `resources/locale/kor.ini`
- 서버에서 수정 가능한 로케일 파일:
  - `plugin_data/AFKMine/locale/*.ini`

### 다국어 기본 config

- 리소스 config:
  - `resources/config/eng.yml` (필수)
  - `resources/config/kor.yml`
- 첫 실행 시(또는 `plugin_data/AFKMine/config.yml`이 없을 때) 서버 언어에 맞는 config가 다음 위치로 저장됩니다.
  - `plugin_data/AFKMine/config.yml`

##

-----

## 광산 삭제 시 원본 블럭 복구

광산 생성 단계에서 광물 포인트를 지정할 때 해당 좌표의 원본 블럭 stateId를 기록합니다.
광산을 삭제(` /afkmineadmin delete <name>` )하면 기록된 stateId를 이용해 광물 포인트 위치의 블럭을
삭제 전 원래 상태로 복구합니다.

##

-----

## 설치

1) 플러그인 `.phar` 릴리스를 다운로드 (또는 PoC 테스트 목적이라면 소스 설치)
2) 다운로드한 `.phar` 파일을 서버의 **/plugins/** 폴더에 이동
3) 서버 재시작

##

-----

## License

자세한 내용은 레포지토리의 라이선스 파일을 확인해주세요.

##

-----

[version-badge]: https://img.shields.io/github/v/release/presentkim-pm/AfkMine?display_name=tag&style=for-the-badge&label=VERSION
[stars-badge]: https://img.shields.io/github/stars/presentkim-pm/AfkMine.svg?style=for-the-badge
[license-badge]: https://img.shields.io/github/license/presentkim-pm/AfkMine.svg?style=for-the-badge

[stars-url]: https://github.com/presentkim-pm/AfkMine/stargazers
[issues-url]: https://github.com/presentkim-pm/AfkMine/issues
[license-url]: https://github.com/presentkim-pm/AfkMine/blob/main/LICENSE

