# Changelog

All notable changes to this project will be documented in this file.

## Unreleased

### BREAKING
- **Removed all 344 vendored Amsterdam Design System icon SVGs from `img/icons/`.** The
  upstream `@amsterdam/design-system-assets` `LICENSE.md` declares the icon artwork
  **proprietary to the City of Amsterdam** ("The open-source licence does NOT apply to
  files in this directory"), restricted to contexts where Amsterdam is the main
  communicator. nldesign shipping this artwork to arbitrary Dutch-government Nextcloud
  instances was exactly the redistribution its notice forbids, and the app's own
  `img/ICONS.md`/spec incorrectly claimed MPL-2.0. There is no grace release for the
  artwork itself — it is gone now. `@amsterdam/design-system-assets` and
  `@amsterdam/design-system-react-icons` are removed from `package.json`.
- **New icon source: `@conduction/nextcloud-vue` (devDependency, build-time only).**
  `scripts/build-icons.js` now materializes the three EUPL-compatible NL-government icon
  packs it bundles into `img/icons/{set}/{key}.svg`:
  - `img/icons/rvo/` — 1163 icons, RVO / ROOS, **CC0-1.0**
  - `img/icons/open-gemeenten/` — 256 icons, OpenGemeenten Iconenset, **CC0-1.0**
  - `img/icons/den-haag/` — 69 icons, Gemeente Den Haag, **EUPL-1.2**

  1488 icons total. The PHP-side consumption contract is unchanged in shape:
  `imagePath('nldesign', 'icons/{set}/{key}.svg')`. No runtime JS dependency on nc-vue —
  nldesign remains a no-Vue app.
- **One-release legacy filename aliases, then removal in the next minor release.** For
  exactly this release, a curated map (`scripts/icon-aliases.json`) additionally writes
  **replacement artwork** (never Amsterdam bytes) under 77 of the 344 legacy Amsterdam
  filenames at the old top-level path `img/icons/{Name}.svg`, so stored consumer URLs
  (e.g. launchpad tile data persisting `/apps/nldesign/img/icons/{Name}.svg`) keep
  resolving for one release. The other 267 legacy names have no reasonable equivalent and
  return **HTTP 404 immediately**. The full table:

  | Legacy filename | Replacement (this release only) |
  | --- | --- |
| `Airplane.svg` | *(no equivalent — 404)* |
| `Apple.svg` | *(no equivalent — 404)* |
| `AppleFill.svg` | *(no equivalent — 404)* |
| `Area.svg` | *(no equivalent — 404)* |
| `AreaFill.svg` | *(no equivalent — 404)* |
| `ArrowBackward.svg` | `den-haag/dh-arrows-arrow-left.svg` |
| `ArrowDown.svg` | `rvo/rvo-pijl-omlaag.svg` |
| `ArrowForward.svg` | `den-haag/dh-arrows-arrow-right.svg` |
| `ArrowUp.svg` | `rvo/rvo-pijl-omhoog.svg` |
| `AwardRibbon.svg` | *(no equivalent — 404)* |
| `AwardRibbonFill.svg` | *(no equivalent — 404)* |
| `BabyBottle.svg` | *(no equivalent — 404)* |
| `BabyBottleFill.svg` | *(no equivalent — 404)* |
| `Ball.svg` | *(no equivalent — 404)* |
| `BankCard.svg` | *(no equivalent — 404)* |
| `BankCardFill.svg` | *(no equivalent — 404)* |
| `BarChart.svg` | *(no equivalent — 404)* |
| `BarChartFill.svg` | *(no equivalent — 404)* |
| `Bed.svg` | `den-haag/dh-objects-bed.svg` |
| `Bell.svg` | *(no equivalent — 404)* |
| `BellFill.svg` | *(no equivalent — 404)* |
| `Bike.svg` | *(no equivalent — 404)* |
| `Bread.svg` | *(no equivalent — 404)* |
| `BreadFill.svg` | *(no equivalent — 404)* |
| `Broom.svg` | *(no equivalent — 404)* |
| `Brush.svg` | *(no equivalent — 404)* |
| `BrushFill.svg` | *(no equivalent — 404)* |
| `Building.svg` | `den-haag/dh-objects-building.svg` |
| `BuildingFill.svg` | *(no equivalent — 404)* |
| `Buildings.svg` | *(no equivalent — 404)* |
| `BuildingsFill.svg` | *(no equivalent — 404)* |
| `Bus.svg` | *(no equivalent — 404)* |
| `BusFill.svg` | *(no equivalent — 404)* |
| `Cake.svg` | *(no equivalent — 404)* |
| `CakeFill.svg` | *(no equivalent — 404)* |
| `Calendar.svg` | `den-haag/dh-objects-calendar.svg` |
| `CalendarFill.svg` | `rvo/rvo-kalender.svg` |
| `Camera.svg` | *(no equivalent — 404)* |
| `CameraFill.svg` | *(no equivalent — 404)* |
| `Car.svg` | `den-haag/dh-objects-car.svg` |
| `CarFill.svg` | *(no equivalent — 404)* |
| `Certificate.svg` | *(no equivalent — 404)* |
| `CertificateFill.svg` | *(no equivalent — 404)* |
| `ChargingStation.svg` | *(no equivalent — 404)* |
| `ChargingStationFill.svg` | *(no equivalent — 404)* |
| `CheckMark.svg` | `den-haag/dh-functional-checked.svg` |
| `CheckMarkCircle.svg` | `den-haag/dh-informational-checkcircle.svg` |
| `CheckMarkCircleFill.svg` | `den-haag/dh-informational-checkcircle.svg` |
| `ChevronBackward.svg` | `den-haag/dh-arrows-chevron-left.svg` |
| `ChevronDown.svg` | `den-haag/dh-arrows-chevron-down.svg` |
| `ChevronForward.svg` | `den-haag/dh-arrows-chevron-right.svg` |
| `ChevronUp.svg` | `den-haag/dh-arrows-chevron-up.svg` |
| `CityPass.svg` | *(no equivalent — 404)* |
| `CityPassFill.svg` | *(no equivalent — 404)* |
| `Clipboard.svg` | `den-haag/dh-objects-clipboard.svg` |
| `ClipboardFill.svg` | *(no equivalent — 404)* |
| `Clock.svg` | *(no equivalent — 404)* |
| `ClockFill.svg` | *(no equivalent — 404)* |
| `Close.svg` | `den-haag/dh-functional-close.svg` |
| `Cogwheel.svg` | `den-haag/dh-informational-settings.svg` |
| `CogwheelFill.svg` | `den-haag/dh-informational-settings.svg` |
| `ConnectedCircles.svg` | *(no equivalent — 404)* |
| `ConnectedCirclesFill.svg` | *(no equivalent — 404)* |
| `Construction.svg` | *(no equivalent — 404)* |
| `Contrast.svg` | *(no equivalent — 404)* |
| `ContrastFill.svg` | *(no equivalent — 404)* |
| `Cookie.svg` | *(no equivalent — 404)* |
| `CookieFill.svg` | *(no equivalent — 404)* |
| `Copy.svg` | *(no equivalent — 404)* |
| `CopyFill.svg` | *(no equivalent — 404)* |
| `CrossHair.svg` | *(no equivalent — 404)* |
| `CrossHairFill.svg` | *(no equivalent — 404)* |
| `Database.svg` | *(no equivalent — 404)* |
| `DatabaseFill.svg` | *(no equivalent — 404)* |
| `Databases.svg` | *(no equivalent — 404)* |
| `DatabasesFill.svg` | *(no equivalent — 404)* |
| `Delete.svg` | `den-haag/dh-functional-trash.svg` |
| `DeleteFill.svg` | *(no equivalent — 404)* |
| `Document.svg` | `den-haag/dh-objects-document.svg` |
| `DocumentCheckMark.svg` | *(no equivalent — 404)* |
| `DocumentCheckMarkFill.svg` | *(no equivalent — 404)* |
| `DocumentEuro.svg` | *(no equivalent — 404)* |
| `DocumentEuroFill.svg` | *(no equivalent — 404)* |
| `DocumentFill.svg` | `rvo/rvo-document-blanco.svg` |
| `DocumentMusicalNote.svg` | *(no equivalent — 404)* |
| `DocumentMusicalNoteFill.svg` | *(no equivalent — 404)* |
| `DocumentPercent.svg` | *(no equivalent — 404)* |
| `DocumentPercentFill.svg` | *(no equivalent — 404)* |
| `DocumentQuestionMark.svg` | *(no equivalent — 404)* |
| `DocumentQuestionMarkFill.svg` | *(no equivalent — 404)* |
| `DocumentWithPencil.svg` | *(no equivalent — 404)* |
| `DocumentWithPencilFill.svg` | *(no equivalent — 404)* |
| `Documents.svg` | *(no equivalent — 404)* |
| `DocumentsFill.svg` | *(no equivalent — 404)* |
| `Download.svg` | `den-haag/dh-functional-download.svg` |
| `Duplicate.svg` | *(no equivalent — 404)* |
| `DuplicateFill.svg` | *(no equivalent — 404)* |
| `Earth.svg` | *(no equivalent — 404)* |
| `EarthFill.svg` | *(no equivalent — 404)* |
| `Ellipsis.svg` | *(no equivalent — 404)* |
| `Embed.svg` | *(no equivalent — 404)* |
| `Error.svg` | `den-haag/dh-informational-circle-warning.svg` |
| `ErrorFill.svg` | `den-haag/dh-informational-circle-warning.svg` |
| `Euro.svg` | `den-haag/dh-objects-euro.svg` |
| `EuroCoins.svg` | *(no equivalent — 404)* |
| `EuroCoinsFill.svg` | *(no equivalent — 404)* |
| `EyeClosed.svg` | `den-haag/dh-functional-hide.svg` |
| `EyeClosedFill.svg` | *(no equivalent — 404)* |
| `EyeOpen.svg` | `den-haag/dh-functional-show.svg` |
| `EyeOpenFill.svg` | *(no equivalent — 404)* |
| `FaceHappy.svg` | *(no equivalent — 404)* |
| `FaceHappyFill.svg` | *(no equivalent — 404)* |
| `FaceNeutral.svg` | *(no equivalent — 404)* |
| `FaceNeutralFill.svg` | *(no equivalent — 404)* |
| `FaceSad.svg` | *(no equivalent — 404)* |
| `FaceSadFill.svg` | *(no equivalent — 404)* |
| `Facebook.svg` | `den-haag/dh-social-facebook.svg` |
| `FastForward.svg` | *(no equivalent — 404)* |
| `Filter.svg` | *(no equivalent — 404)* |
| `FilterFill.svg` | *(no equivalent — 404)* |
| `Flower.svg` | *(no equivalent — 404)* |
| `FlowerFill.svg` | *(no equivalent — 404)* |
| `Folder.svg` | `den-haag/dh-functional-folder.svg` |
| `FolderFill.svg` | *(no equivalent — 404)* |
| `FontSize.svg` | *(no equivalent — 404)* |
| `ForkAndKnife.svg` | *(no equivalent — 404)* |
| `ForkAndKnifeFill.svg` | *(no equivalent — 404)* |
| `FormattingBold.svg` | *(no equivalent — 404)* |
| `FormattingItalic.svg` | *(no equivalent — 404)* |
| `FormattingStrikethrough.svg` | *(no equivalent — 404)* |
| `FormattingUnderline.svg` | *(no equivalent — 404)* |
| `FullScreenClose.svg` | *(no equivalent — 404)* |
| `FullScreenOpen.svg` | *(no equivalent — 404)* |
| `GasolinePump.svg` | *(no equivalent — 404)* |
| `GasolinePumpFill.svg` | *(no equivalent — 404)* |
| `Gavel.svg` | *(no equivalent — 404)* |
| `GavelFill.svg` | *(no equivalent — 404)* |
| `GraduateHat.svg` | *(no equivalent — 404)* |
| `GraduateHatFill.svg` | *(no equivalent — 404)* |
| `Grid.svg` | `den-haag/dh-functional-grid.svg` |
| `GridFill.svg` | *(no equivalent — 404)* |
| `HandWithEuroCoin.svg` | *(no equivalent — 404)* |
| `HandWithPlant.svg` | *(no equivalent — 404)* |
| `HandWithPlantFill.svg` | *(no equivalent — 404)* |
| `Handshake.svg` | *(no equivalent — 404)* |
| `Heart.svg` | `den-haag/dh-functional-favorite.svg` |
| `HeartBroken.svg` | *(no equivalent — 404)* |
| `HeartBrokenFill.svg` | *(no equivalent — 404)* |
| `HeartFill.svg` | `den-haag/dh-functional-favorite.svg` |
| `History.svg` | *(no equivalent — 404)* |
| `House.svg` | `den-haag/dh-objects-house.svg` |
| `HouseCanal.svg` | *(no equivalent — 404)* |
| `HouseCanalFill.svg` | *(no equivalent — 404)* |
| `HouseFill.svg` | `rvo/rvo-home.svg` |
| `HouseWithFlag.svg` | *(no equivalent — 404)* |
| `HouseWithFlagFill.svg` | *(no equivalent — 404)* |
| `IdentityCard.svg` | *(no equivalent — 404)* |
| `IdentityCardFill.svg` | *(no equivalent — 404)* |
| `Image.svg` | `den-haag/dh-objects-image.svg` |
| `ImageFill.svg` | *(no equivalent — 404)* |
| `Incognito.svg` | *(no equivalent — 404)* |
| `IncognitoFill.svg` | *(no equivalent — 404)* |
| `Info.svg` | `den-haag/dh-informational-circle-information.svg` |
| `InfoFill.svg` | `den-haag/dh-informational-circle-information.svg` |
| `Instagram.svg` | `den-haag/dh-social-instagram.svg` |
| `KeyboardKeyCommand.svg` | *(no equivalent — 404)* |
| `KeyboardKeyControl.svg` | *(no equivalent — 404)* |
| `KeyboardKeyEnter.svg` | *(no equivalent — 404)* |
| `KeyboardKeyShift.svg` | *(no equivalent — 404)* |
| `LaptopBroken.svg` | *(no equivalent — 404)* |
| `Layers.svg` | *(no equivalent — 404)* |
| `LayersFill.svg` | *(no equivalent — 404)* |
| `Leaf.svg` | *(no equivalent — 404)* |
| `LeafFill.svg` | *(no equivalent — 404)* |
| `LightBulb.svg` | *(no equivalent — 404)* |
| `LightBulbFill.svg` | *(no equivalent — 404)* |
| `Lightning.svg` | *(no equivalent — 404)* |
| `LightningFill.svg` | *(no equivalent — 404)* |
| `LineChartDown.svg` | *(no equivalent — 404)* |
| `LineChartUp.svg` | *(no equivalent — 404)* |
| `Link.svg` | *(no equivalent — 404)* |
| `LinkExternal.svg` | `den-haag/dh-functional-external-link.svg` |
| `LinkExternalFill.svg` | *(no equivalent — 404)* |
| `LinkedIn.svg` | `den-haag/dh-social-linkedin.svg` |
| `List.svg` | `den-haag/dh-functional-list.svg` |
| `LockClosed.svg` | *(no equivalent — 404)* |
| `LockClosedFill.svg` | *(no equivalent — 404)* |
| `LockOpen.svg` | *(no equivalent — 404)* |
| `LockOpenFill.svg` | *(no equivalent — 404)* |
| `LogIn.svg` | *(no equivalent — 404)* |
| `LogOut.svg` | `den-haag/dh-functional-log-out.svg` |
| `MagnifyingGlassWithEye.svg` | *(no equivalent — 404)* |
| `MagnifyingGlassWithEyeFill.svg` | *(no equivalent — 404)* |
| `Mail.svg` | `den-haag/dh-communication-email.svg` |
| `MailFill.svg` | `rvo/rvo-mail.svg` |
| `Map.svg` | `den-haag/dh-objects-map.svg` |
| `MapFill.svg` | *(no equivalent — 404)* |
| `MapMarker.svg` | `rvo/rvo-locatiemarker.svg` |
| `MapMarkerFill.svg` | `rvo/rvo-locatiemarker.svg` |
| `MapMarkerOnMap.svg` | *(no equivalent — 404)* |
| `MapMarkerOnMapFill.svg` | *(no equivalent — 404)* |
| `MarketStall.svg` | *(no equivalent — 404)* |
| `Mastodon.svg` | *(no equivalent — 404)* |
| `Maximize.svg` | *(no equivalent — 404)* |
| `MedicalKit.svg` | *(no equivalent — 404)* |
| `MedicalKitFill.svg` | *(no equivalent — 404)* |
| `Megaphone.svg` | *(no equivalent — 404)* |
| `MegaphoneFill.svg` | *(no equivalent — 404)* |
| `Menu.svg` | `den-haag/dh-functional-hamburger.svg` |
| `Minimize.svg` | *(no equivalent — 404)* |
| `Minus.svg` | *(no equivalent — 404)* |
| `MinusCircle.svg` | *(no equivalent — 404)* |
| `MinusCircleFill.svg` | *(no equivalent — 404)* |
| `Monitor.svg` | *(no equivalent — 404)* |
| `Monument.svg` | *(no equivalent — 404)* |
| `MonumentFill.svg` | *(no equivalent — 404)* |
| `Next.svg` | *(no equivalent — 404)* |
| `Notification.svg` | *(no equivalent — 404)* |
| `NotificationFill.svg` | *(no equivalent — 404)* |
| `OrganisationChart.svg` | *(no equivalent — 404)* |
| `Park.svg` | *(no equivalent — 404)* |
| `ParkFill.svg` | *(no equivalent — 404)* |
| `Parking.svg` | *(no equivalent — 404)* |
| `ParkingBike.svg` | *(no equivalent — 404)* |
| `ParkingFill.svg` | *(no equivalent — 404)* |
| `ParkingGarage.svg` | *(no equivalent — 404)* |
| `Passport.svg` | *(no equivalent — 404)* |
| `PassportFill.svg` | *(no equivalent — 404)* |
| `Pause.svg` | *(no equivalent — 404)* |
| `Pen.svg` | `den-haag/dh-functional-edit.svg` |
| `PenFill.svg` | *(no equivalent — 404)* |
| `Pencil.svg` | `den-haag/dh-functional-edit.svg` |
| `PencilFill.svg` | *(no equivalent — 404)* |
| `Person.svg` | `den-haag/dh-objects-user.svg` |
| `PersonAtDesk.svg` | *(no equivalent — 404)* |
| `PersonAtDeskFill.svg` | *(no equivalent — 404)* |
| `PersonCircle.svg` | *(no equivalent — 404)* |
| `PersonCircleFill.svg` | *(no equivalent — 404)* |
| `PersonFill.svg` | `rvo/rvo-user.svg` |
| `PersonInWheelchair.svg` | *(no equivalent — 404)* |
| `PersonInWheelchairMoving.svg` | *(no equivalent — 404)* |
| `PersonPraying.svg` | *(no equivalent — 404)* |
| `PersonSwimming.svg` | *(no equivalent — 404)* |
| `PersonSwimmingFill.svg` | *(no equivalent — 404)* |
| `Persons.svg` | `rvo/rvo-groep-3-personen.svg` |
| `PersonsFill.svg` | *(no equivalent — 404)* |
| `PersonsWithEuroCoin.svg` | *(no equivalent — 404)* |
| `PersonsWithEuroCoinFill.svg` | *(no equivalent — 404)* |
| `Phone.svg` | `den-haag/dh-communication-call.svg` |
| `PhoneFill.svg` | `rvo/rvo-telefoon.svg` |
| `PieChart.svg` | *(no equivalent — 404)* |
| `PieChartFill.svg` | *(no equivalent — 404)* |
| `PiggyBank.svg` | *(no equivalent — 404)* |
| `PiggyBankFill.svg` | *(no equivalent — 404)* |
| `Placeholder.svg` | *(no equivalent — 404)* |
| `Plant.svg` | *(no equivalent — 404)* |
| `PlantFill.svg` | *(no equivalent — 404)* |
| `Play.svg` | *(no equivalent — 404)* |
| `Plus.svg` | *(no equivalent — 404)* |
| `PlusCircle.svg` | *(no equivalent — 404)* |
| `PlusCircleFill.svg` | *(no equivalent — 404)* |
| `PowerPlug.svg` | *(no equivalent — 404)* |
| `PowerPlugFill.svg` | *(no equivalent — 404)* |
| `PowerPlugWithSocket.svg` | *(no equivalent — 404)* |
| `PowerPlugWithSocketFill.svg` | *(no equivalent — 404)* |
| `Pregnant.svg` | *(no equivalent — 404)* |
| `PregnantFill.svg` | *(no equivalent — 404)* |
| `Previous.svg` | *(no equivalent — 404)* |
| `Print.svg` | `rvo/rvo-printer.svg` |
| `PrintFill.svg` | *(no equivalent — 404)* |
| `QuestionMarkCircle.svg` | `den-haag/dh-informational-circle-help.svg` |
| `QuestionMarkCircleFill.svg` | `den-haag/dh-informational-circle-help.svg` |
| `QuotationMarkClose.svg` | *(no equivalent — 404)* |
| `QuotationMarkOpen.svg` | *(no equivalent — 404)* |
| `Redo.svg` | *(no equivalent — 404)* |
| `Replay.svg` | *(no equivalent — 404)* |
| `Rewind.svg` | *(no equivalent — 404)* |
| `Ruler.svg` | *(no equivalent — 404)* |
| `RulerFill.svg` | *(no equivalent — 404)* |
| `SaintAndrewsCrosses.svg` | *(no equivalent — 404)* |
| `Save.svg` | *(no equivalent — 404)* |
| `SaveFill.svg` | *(no equivalent — 404)* |
| `Search.svg` | `den-haag/dh-functional-search.svg` |
| `Settings.svg` | `den-haag/dh-informational-settings.svg` |
| `SettingsFill.svg` | `den-haag/dh-informational-settings.svg` |
| `Share.svg` | `den-haag/dh-functional-share.svg` |
| `SpeechBalloonEllipsis.svg` | *(no equivalent — 404)* |
| `SpeechBalloonEllipsisFill.svg` | *(no equivalent — 404)* |
| `SpeechBalloonNotification.svg` | *(no equivalent — 404)* |
| `SpeechBalloonNotificationFill.svg` | *(no equivalent — 404)* |
| `SpeechBalloonQuestionMark.svg` | *(no equivalent — 404)* |
| `SpeechBalloonQuestionMarkFill.svg` | *(no equivalent — 404)* |
| `SportsField.svg` | *(no equivalent — 404)* |
| `SportsFieldFill.svg` | *(no equivalent — 404)* |
| `StandBy.svg` | *(no equivalent — 404)* |
| `Star.svg` | `den-haag/dh-objects-star.svg` |
| `StarFill.svg` | `rvo/rvo-ster.svg` |
| `Stroller.svg` | *(no equivalent — 404)* |
| `StrollerFill.svg` | *(no equivalent — 404)* |
| `Student.svg` | *(no equivalent — 404)* |
| `StudentFill.svg` | *(no equivalent — 404)* |
| `Success.svg` | `den-haag/dh-informational-checkcircle.svg` |
| `SuccessFill.svg` | `den-haag/dh-informational-checkcircle.svg` |
| `Suitcase.svg` | *(no equivalent — 404)* |
| `SuitcaseFill.svg` | *(no equivalent — 404)* |
| `SunbedParasol.svg` | *(no equivalent — 404)* |
| `SunbedParasolFill.svg` | *(no equivalent — 404)* |
| `Syringe.svg` | *(no equivalent — 404)* |
| `SyringeFill.svg` | *(no equivalent — 404)* |
| `Table.svg` | *(no equivalent — 404)* |
| `TableFill.svg` | *(no equivalent — 404)* |
| `TaxLetter.svg` | *(no equivalent — 404)* |
| `TaxLetterFill.svg` | *(no equivalent — 404)* |
| `ThumbsDown.svg` | *(no equivalent — 404)* |
| `ThumbsDownFill.svg` | *(no equivalent — 404)* |
| `ThumbsUp.svg` | *(no equivalent — 404)* |
| `ThumbsUpFill.svg` | *(no equivalent — 404)* |
| `TrashBag.svg` | *(no equivalent — 404)* |
| `TrashBagFill.svg` | *(no equivalent — 404)* |
| `TrashBin.svg` | `den-haag/dh-functional-trash.svg` |
| `TrashBinFill.svg` | *(no equivalent — 404)* |
| `Tree.svg` | *(no equivalent — 404)* |
| `TreeFill.svg` | *(no equivalent — 404)* |
| `TreeWide.svg` | *(no equivalent — 404)* |
| `TreeWideFill.svg` | *(no equivalent — 404)* |
| `Undo.svg` | *(no equivalent — 404)* |
| `Upload.svg` | `rvo/rvo-upload.svg` |
| `UserAccount.svg` | `den-haag/dh-objects-user.svg` |
| `UserAccountFill.svg` | `rvo/rvo-user.svg` |
| `Video.svg` | *(no equivalent — 404)* |
| `VideoFill.svg` | *(no equivalent — 404)* |
| `VolumeOff.svg` | *(no equivalent — 404)* |
| `VolumeOffFill.svg` | *(no equivalent — 404)* |
| `VolumeOn.svg` | *(no equivalent — 404)* |
| `VolumeOnFill.svg` | *(no equivalent — 404)* |
| `VotingBallot.svg` | *(no equivalent — 404)* |
| `Wallet.svg` | `den-haag/dh-objects-wallet.svg` |
| `WalletFill.svg` | *(no equivalent — 404)* |
| `Warning.svg` | `den-haag/dh-informational-alert-triangle.svg` |
| `WarningFill.svg` | `den-haag/dh-informational-alert-triangle-filled.svg` |
| `WaterLadder.svg` | *(no equivalent — 404)* |
| `Whatsapp.svg` | `den-haag/dh-social-whatsapp.svg` |
| `WiFi.svg` | *(no equivalent — 404)* |
| `X.svg` | `den-haag/dh-social-twitter-x.svg` |

  **These 77 alias files are removed in the next minor release** — `scripts/icon-aliases.json`
  will be emptied and the build will stop emitting top-level legacy files. Known consumer:
  launchpad's archived tiles design persisted `iconType: 'url'` values of the form
  `/apps/nldesign/img/icons/{Name}.svg` in tile data; a follow-up issue tracks migrating
  `launchpad/src/components/__tests__/TileEditor.spec.js` (and any stored-tile data) to the
  new paths before the alias-removal release.
- `img/logos/` (23 organisation logos) is unaffected — those are static, checked-in
  huisstijl assets tied to `token-sets.json` `theming.logo` entries, not icon-library
  redistribution, and `scripts/build-icons.js` no longer touches that directory at all.

### Added
- **Token-set vocabulary audit — "the examples look correct" is now a test, not an opinion.**
  A new `TokenSetVocabularyAuditService` answers the question no existing gate could:
  does a shipped token set actually declare the `--nldesign-*` tokens its design system
  reads? Three mechanical rules per set — the 26 required semantic tokens it must declare
  itself, `--nldesign-*` names it declares that no stylesheet reads, and disagreement
  between its `--nldesign-color-primary` and `token-sets.json`'s `theming.primary_color`.
  Sets whose design system reads no `--nldesign-*` name at all (`none`, `summer-breeze`)
  are reported as not auditable rather than as failing.

  The measured baseline: of 48 shipped sets, **5 are complete** (`amsterdam`,
  `conduction`, `denhaag`, `utrecht`, `vng`), 2 are not auditable, and **41 are
  incomplete** — they fall through to `css/systems/nldesign/defaults.css` and therefore
  render as Rijkshuisstijl rather than as their own brand. Those 41 are recorded in
  `tests/Unit/fixtures/token-set-vocabulary-allowlist.json` so CI stays green; the new
  `tests/Unit/TokenSetVocabularyTest.php` fails both on a set that is incomplete and
  unlisted AND on a listed set that has started passing, so the list can only shrink. No
  token set file is changed by this release — regenerating them is the next step.
- **`npm run audit:token-sets`** — a dependency-free Node mirror of the same three rules
  (`scripts/audit-token-sets.mjs`), so a token-set author can see the verdict without a
  PHP runtime or a `composer install`. Prints a per-set table; `--verbose` names every
  offending token, `--json` emits machine-readable results, and
  `npm run audit:token-sets:check` exits non-zero on a regression.
- **"Incomplete set" badge in the admin settings page.** A third badge state next to the
  design-system and WCAG badges, hidden for a complete set, with a tooltip listing exactly
  which required tokens are missing, which declared names nothing reads, and any
  primary-colour disagreement. The apply dialog gains a matching non-blocking banner
  stating that the missing tokens fall back to the Rijkshuisstijl defaults. Carried on the
  existing `warnings` channel, distinguished by `kind: 'incomplete'`.
- **Theme-switchable iconography — new `dsfr` icon pack + resolver.** The bundled icon
  set an app resolves through nldesign now travels with the active **design system**, so
  a French-government (`lasuite`) instance serves French-government icons and a
  Dutch-government (`nldesign`) instance keeps serving the existing Dutch icons — driven
  by the active design system / token set, not hardcoded per consumer. This is purely
  **additive**: no `rvo`/`open-gemeenten`/`den-haag` icon key is renamed or removed, and
  every existing `imagePath('nldesign', 'icons/{set}/{key}.svg')` URL keeps resolving
  byte-for-byte.
  - **New icon source: `@gouvfr/dsfr` (devDependency, build-time only).**
    `scripts/build-icons.js` gained a `glob` pack kind materializing the French-government
    DSFR icon set (`dist/icons/**/*.svg`, real SVG files, category subdirectories dropped)
    into `img/icons/dsfr/{basename}.svg`:
    - `img/icons/dsfr/` — 1038 icons, Système de Design de l'État (DSFR), **Etalab-2.0**
    - This is **icons only** — the Marianne typeface files shipped in the same
      `@gouvfr/dsfr` package are FR-state-restricted and are never bundled.
    - `@gouvfr/dsfr` currently cannot be `npm install`-ed (broken optional dependencies
      upstream); the build script reads the DSFR source SVGs from the real npm package
      path when present, falling back to a pre-fetched, gitignored scratch directory
      (`.dsfr-src/icons/`) otherwise. Either way the *committed* output is the same
      `img/icons/dsfr/` tree.
  - **`icon_pack` field on `design-systems.json`.** An optional ordered pack-directory
    list (or single-string shorthand) per design system: `nldesign` ->
    `["rvo", "open-gemeenten", "den-haag"]` (the pre-existing Dutch default — no behaviour
    change for any current NL theme), `lasuite` -> `"dsfr"`. `none`/`summer-breeze`/
    `high-contrast`/`cunningham` declare no `icon_pack` — Nextcloud stock icons apply,
    unchanged from today.
  - **`DesignSystemService::getIconPacks()` / `resolveActiveIconPacks()` /
    `resolveIconPath()`.** Resolution chain: active token set -> its `design_system` ->
    that design system's `icon_pack`, with an appconfig `icon_pack` admin override taking
    precedence when it names a real pack directory (`occ config:app:set nldesign
    icon_pack --value=dsfr`). Always degrades safely to `[]` (no pack) — never throws.
  - **Public capability:** `Capabilities` now advertises the resolved pack list as
    `iconPacks` (`/ocs/v2.php/cloud/capabilities` -> `capabilities.nldesign.iconPacks`),
    so other apps can read the active pack without duplicating the resolution logic. The
    degrade (minimal) payload sets `iconPacks: []`.
  - **Read-only admin indicator** on the nldesign settings page showing the active pack,
    its source (design system vs. admin override), and the honest limitation that this
    switches nldesign's own bundled icon assets — it does not force-replace Nextcloud
    core's built-in icons beyond what the active theme's CSS already restyles.
  - See `openspec/specs/icon-packs/spec.md` (new) and `openspec/specs/icon-assets/spec.md`
    (extended) for the full contract.

### Changed
- `css/custom-css.css` is now gitignored, next to `css/custom-overrides.css`. It is admin-authored runtime data written on demand by `CustomCssService::write()`, never app source — `CssInjectionService` only emits the stylesheet when the freeform-CSS feature is enabled and the file has content, so its absence is the correct default state.
- Style injection moved from `Application::boot()` (every request) to a `ThemeInjectionListener` on `BeforeTemplateRenderedEvent`/`BeforeLoginTemplateRenderedEvent` (only actual template renders) — same stylesheets, same cascade order, same excluded-app behavior by default. Adds an occ-only `themed_contexts` appconfig key to selectively unthemed a render context (`user`/`login`/`guest`/`public`/`error`); absent (the default) themes every context exactly as before. See `openspec/changes/render-event-injection/`.

### Security
- Hardened `CustomTokenSetValidator::isForbiddenValue()` to reject declaration values containing a semicolon (`;`) or a CSS comment marker (`/*`, `*/`), closing a CSS-injection gap where a single accepted `--nldesign-*`/`--{slug}-*` declaration's value could smuggle an arbitrary extra declaration (e.g. `background: url(...)`) past the name whitelist into the `:root {}` block served to every anonymous visitor (login page, share links). Applies to both the CSS upload path and the W3C Design Tokens JSON path (`CustomTokenSetController::mapFromJson()`), which shares the same gate. Only new uploads are affected — a custom token set uploaded before this fix is not retroactively re-validated; the served `custom-*.css` file for an existing set is unchanged until it is re-uploaded. See `openspec/changes/harden-custom-token-set-value-validation/`.

### Fixed
- **Every themed page sat ~56px too low, with the page background showing as a band between
  the header and the content container.** `#header` carried a
  `position: relative !important`, which put Nextcloud's out-of-flow (`absolute`) header back
  into normal flow. `#content` is `position: fixed` with `top: auto`, so its offset resolves
  against its static position — which then started *below* the 50px flowed header, and its own
  `margin-top: var(--header-height)` stacked on top of that (measured: `contentTop` 106.5px
  themed vs 50px stock). The override also bought nothing: `#header::before`/`::after` are
  disabled in the adjacent rule, the lint/ribbon is `#nextcloud::before` with its own
  positioning, and an `absolute` element is already a containing block for absolute
  descendants.
- **A blue strip appeared between the navigation and the content pane.**
  `margin-right: 30px !important` on `#app-navigation` opened 30px of empty flex space inside
  `#content`, which carries no background of its own, so `--color-background-plain` showed
  straight through. Removed, along with its `.app-navigation--close` counterpart and the
  per-panel `border-radius` on the navigation and content (`#content` already rounds both
  panels together via `overflow: clip`; the container radius stays themed once, through
  `--body-container-radius`).
- **The theming dialogs' sticky action bar let table rows scroll through the strip beneath the
  buttons**, and a wide token table gave the whole dialog a horizontal scrollbar the sticky bar
  could not follow, so cells drifted out beside the buttons. The bar now bleeds into the
  dialog's padding, the dialog no longer scrolls sideways, and wide token tables scroll inside
  their own container.
- Corrected the declared licence in `appinfo/info.xml` from `agpl` to `eupl` (EUPL-1.2) to match the bundled `LICENSE`, the SPDX headers, and the rest of the Conduction fleet. Adopters may key compliance on the declared licence, so the App Store listing now states the correct EUPL-1.2 licence.
- Documentation corrected to describe the real bundled, self-hosted Fira Sans delivery (no external CDN) and the true token-set count derived from `token-sets.json`.
- `docs/reference/token-audit.md` scoped its "production-ready" verdict to the five manually-reviewed sets; contrast for all sets is now verified by the automated contrast audit.

## 0.1.0 - Initial Release

- Initial app structure
- Basic Nextcloud integration
