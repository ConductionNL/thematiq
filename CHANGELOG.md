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
- **The token tables in the apply and theming-sync dialogs hid the column an admin is there
  to read.** Every column was `white-space: nowrap`, so each one sized to its content — and
  one value, the font stack (`-apple-system, BlinkMacSystemFont, 'Segoe UI', …`), is wider
  than the 700px dialog by itself. That pushed the **New** column behind a horizontal
  scrollbar, so the dialog appeared to list only the values already applied and none of the
  ones being offered. The two value columns now cap at 230px and wrap; the checkbox and
  token-name columns still do not.
- **Both dialogs listed bare hex strings with no colour next to them.** `js/admin.js` has
  always emitted a `.nldesign-dialog-swatch` / `.nldesign-apply-swatch` span and set its
  colour inline, but the rules giving those spans a box were lost in a stylesheet
  reorganisation, and a span with no `display`, width or height is zero pixels wide. They
  are sized again, so a colour change is judged by the colour rather than by reading hex.
- **Rows kept rendering below the action buttons in any dialog carrying a token table.** The
  button bar used `position: sticky; bottom: 0`, which pins to the bottom of the
  SCROLLPORT, not of the dialog. A dialog with a table is now a flex column — heading, hint
  and select-all row fixed, the table the only scrolling child (`min-height: 0`, or it
  refuses to shrink below its content and pushes the bar out again), the bar the last flex
  item, so it cannot be anywhere but the bottom. Scoped with `:has()`, because the dialogs
  without a table are short and should keep growing with their content.
- **On every themed Vue app — the Files app included — the page background showed through the
  content panel.** The rule making the dashboard's panel transparent, so the instance's
  background image can show through it, was unscoped: `body #content main.app-content`
  matches every Vue app's main element, and the Files panel is exactly
  `<main id="app-content-vue" class="app-content">`. With `#content` above it transparent
  too, the page background appeared as a coloured line down the seam between navigation and
  content, a wedge in the notch of the container's rounded top corner, and a frame along the
  right and bottom edges. The rule is now scoped with the app class `#content` already
  carries (`.app-dashboard`), so the two panels read as one sheet clipped by `#content`,
  which is only true while both are opaque.
- **The active app was marked twice in the header, once invisibly.** Nextcloud already draws
  that mark: `.app-menu-entry--active::before`, a 10×5 rounded pill under the icon, painted
  with `--color-background-plain-text` — the colour core computes against the PAGE
  background, because a stock header is transparent and the pill sits on that background.
  These bundles paint the header, so on a set that paints it white (Rijkshuisstijl,
  Amsterdam, Cunningham) core's pill came out white on white. Both bundles then drew a
  SECOND mark no stock instance has: a full-width bar under the entry, 5px in the nldesign
  bundle and 3px in La Suite. Core's pill now keeps its geometry and takes a colour it is
  legible against — the header's own foreground in the nldesign bundle, the brand colour in
  La Suite — and the extra bar is gone.
- **A brand with square controls squared off the entire app shell.** `--body-container-radius`
  is a CONTAINER radius, like `--border-radius-container` next to it, but it was mapped to
  the brand's CONTROL radius, and `#content` draws its `border-radius` from it. On a set
  whose controls are near-square (Zwolle: 2px) the whole shell lost its rounding. It is left
  to Nextcloud now: the base value is 0 and only the two top corners round, from
  `--border-radius-large`, which IS themed — so the brand still decides how round the shell
  reads, without a control radius deciding it.
- **The Cunningham set applied its flat 4px radius to the Nextcloud chrome, and painted every
  table header.** Cunningham is a 4px system, and taking that literally squared off the app
  shell, navigation entries, search field, buttons and modals where a stock instance rounds
  them. This set is Nextcloud with Cunningham's COLOURS, so its radius tokens now carry
  Nextcloud 32's own scale (4 / 8 / 8 / 28 / 100) while the brand's 4px stays available to
  NL Design System components through the `--utrecht-*` bridge. Its table headers are
  Nextcloud's too — transparent, muted, weight 400 — where the shared theme painted every
  `th` with the NL Design System table-header colour and a stock Files list shows none.
- **The Cunningham set's `theming.background_color` was pure white**, which is not a colour
  the set uses anywhere: it is now `#E1E2E5`, the set's own
  `--nldesign-color-background-dark`, so Nextcloud's core theming (login page, plain
  background) matches the background the stylesheet actually paints.
- **On Nextcloud 32, the La Suite / Cunningham search control overflowed its slot and the
  rest of the header-end was drawn on top of it.** The bundle turns NC 32's icon-only search
  trigger into a labelled pill so the control matches what NC 34 renders, but `inline-size:
  auto` grows only the BUTTON to fit its `attr(aria-label)` label — about 130px against the
  ~50px the bar reserves — while the `.header-menu` element around it keeps the icon-only
  width. The flex row therefore laid the next controls over the top: screenshotted on
  Cunningham with the pill running from the app menu to the avatar, the Notifications bell
  and Contacts icon sitting on it, and the label reading "Unified se[bell]rch". Growing the
  container instead needs a `:has()` guard to stay off NC 34 and only works if the trigger is
  in flow, neither of which can be checked against a repository whose bundled Nextcloud is
  30.0.0-dev, so the treatment is withdrawn rather than tuned blind: on NC 32 the control is
  an icon-only trigger again, tinted like the Notifications and Contacts glyphs next to it.
  The NC 34 pill is untouched — none of the removed rules ever matched it — and the block
  records what has to be verified before the pill comes back.
- **The user-status badge was excluded from the forced avatar colour in the La Suite bundle
  too**, for the same reason as in the nldesign one: `avatardiv__user-status` matches
  `[class*='avatar' i] *`, and a status indicator on its own light disc is not text on the
  portrait. That bundle never forced `fill`, which is why its badge stayed green.
- **Every item in the header-end carried an opaque white plate.** `.header-menu` is the class
  on each header-end ITEM — `#unified-search`, `#notifications`, `#contactsmenu` and
  `#user-menu` all have it — and the panel that drops out of one is a child,
  `.header-menu__wrapper` > `.header-menu__content`. A rule written for the panel addressed
  the item instead, so `--color-main-background` was painted behind the account glyphs: a
  white block across the right of Zwolle's blue header, the same block on Rotterdam's
  green, with the body text colour handed to the glyphs on top of it. The rule now addresses
  the panel; the trigger is transparent, as Nextcloud draws it, and the header colour runs
  edge to edge.
- **The user-status badge on the avatar rendered as a black disc instead of the green
  check.** The avatar's protection rule forced `fill: unset`, and because `fill` is an
  inherited property that resolves to `inherit` and walks up to the initial value — black.
  The badge's own markup asks for `fill="var(--user-status-color-online, var(--color-success,
  #2d7b41))"`. The rule no longer touches `fill` or `stop-color` at all: the header-glyph
  rules now exclude the avatar subtree, so there is nothing left for it to undo. It keeps
  `filter: none`, which is load-bearing — core inverts the avatar photo whenever the PAGE
  background is bright, regardless of the header the photo sits on.
- **The account glyphs on the right of the header were painted with the BODY text colour,
  and on a saturated header that made them unfindable.** Search, notifications, contacts and
  the user menu sit on the header, so they take the header's foreground, but the rules
  covering them used `--nldesign-color-text`: Rotterdam drew `#404b4f` glyphs on its
  `#00811f` green at **1.78:1** (its header text is white, 5.05:1), and Zwolle drew pure
  black on its `#476db8` blue where its header text is white. Those glyphs were additionally dimmed
  to `opacity: 0.8`, which spends contrast exactly where a saturated header has none to
  spare — Rotterdam's composited glyph measured 1.05:1. They now take
  `--nldesign-color-header-text` at full strength, with `fill: currentColor` so the SVGs
  follow. The block is named `.header-end` on Nextcloud 32 and `.header-right` in the 30-era
  layout, and only the `.header-end` half had been fixed, so on an installation serving the
  older markup those glyphs were still painted by the legacy rules. Both halves now agree,
  and the `.header-end` rule that forces `fill` on header SVGs no longer reaches into the
  avatar and the status badge either.
- **The avatar and its user-status badge were squares.** `.header-menu *` forced the brand
  radius onto every element in the user menu, including the avatar and the status badge that
  sits on it, which Nextcloud draws as circles (`border-radius: 50%`). A brand radius belongs
  to boxes, not to a portrait; the avatar subtree is excluded and keeps its own shape. The
  badge is also excluded from the two rules that force white onto the avatar's contents —
  those exist to keep the INITIALS legible on the plate, and a status indicator carries its
  own colours on its own light disc.
- **The header logo slot was empty on every token set that ships no logo.** `theme.css`
  blanks Nextcloud's own logo (`background-image: var(--nldesign-logo-url, none)`) so a
  set's artwork can take its place, but roughly twenty shipped sets have no
  `img/logos/<id>.svg` — for those the declaration resolved to `none` and the header simply
  had a 56px hole where a stock installation shows the Nextcloud logo. CSS alone cannot
  recover it: an `!important` declaration whose `var()` chain ends unresolved is still the
  winning declaration and computes to `unset`, so Nextcloud's own rule never returns, and
  its fallback URL is relative to `core/css/server.css`. `CssInjectionService::injectLogoUrl()`
  now supplies the fallback itself, where the webroot is known. An admin-uploaded logo is
  brand artwork and is shown as it is, through the theming app's own `--image-logoheader` /
  `--image-logo` with no filter. With no uploaded logo, core's `logo.svg` is used and
  **masked** to `--nldesign-color-header-text` rather than filtered: the shipped sets paint
  headers from white through ice blue to saturated blue, so no single filter is right for
  all of them, while the header text colour is by definition the one the set says is legible
  on its own header. That is the technique `systems/lasuite/element-overrides.css` already
  documents for the same image. A set that ships artwork is unaffected. The one behaviour
  change beyond the empty slot: `conduction` declared `img/logos/vng.svg` through a relative
  url that resolved to a 404, and now shows the Nextcloud logo instead of a broken image.
- **Every avatar on a themed instance was a rounded square.** The "remove all rounded
  corners" rule in `theme.css` listed `img`, so the brand radius was forced onto every
  image on the page — including the user-menu avatar in the header, the contacts menu,
  share recipients and file previews, all of which Nextcloud draws as circles
  (`.avatardiv { border-radius: 50% }`). `img` is not a component and is no longer in that
  list; the components that take the brand radius stay listed by name.
- **The settings sidebar rendered as a column of underlined brand-coloured links, with the
  selected entry a pale tint instead of Nextcloud's solid selection.** Three shared rules
  fought Nextcloud's own navigation: the blanket link rule handed every `<a>` the link
  colour, the typography section underlined every `<a>`, and `theme.css` repainted the
  active entry with `--nldesign-color-primary-light` plus a 4px left border and brand-
  coloured bold text — which also pushed the selected row 4px out of line with the others
  and put brand text on a brand tint. Navigation entries now take the body text colour
  through the inherited `--nldesign-color-on-surface` opt-out, carry no underline, and the
  selected entry is left to Nextcloud: `--color-primary-element` filled,
  `--color-primary-element-text` labelled, both already mapped to the active set. The
  active app-menu entry in the header keeps its brand accent.
- **The admin preview's app shell did not represent the page it previews.** It drew the app
  navigation as a strip of muted mini-labels and the app sidebar as a second such strip, so
  the panel an admin checks first showed nothing that is actually on screen. The shell now
  mirrors Nextcloud's real geometry: the header spans the page background, the navigation
  and the app content are clipped into one inset rounded container
  (`--body-container-radius`), navigation entries are full-width pills
  (`--border-radius-pill`) with an icon and a body-coloured label of which the selected one
  is filled with `--color-primary-element`, and the sidebar is a panel with a heading and a
  close control. The header bar also reads `--nldesign-header-border-bottom`, and the gap
  around the container reads `--color-background-plain`.
- **Header glyphs were forced to pure black and the avatar to a black square on themed
  pages.** `element-overrides.css` applied `filter: invert(1) brightness(0) contrast(100)` to
  every header-end svg, icon and image: `brightness(0)` ignores the set's header-text token,
  and a filter on an ancestor rasterises its whole subtree, so the user-menu trigger's
  avatar was flattened to a black block that no descendant `filter: none` could rescue. The
  glyphs are now coloured through `color: var(--nldesign-color-header-text)` (they are
  `currentColor` SVGs), the avatar and user-status icon are excluded — the technique the
  lasuite bundle already used, which is why Cunningham rendered correctly. The app menu's
  icons are images and cannot be coloured, so their filter now follows the set through
  `--nldesign-header-icon-filter` (black on the white default header in `defaults.css`;
  `none` on dark headers, where the previous hard-coded black made them vanish), and
  `theme.css` no longer applies that filter to the user-menu trigger or every header svg.
  App-menu labels follow the header text colour instead of the body text colour.
- **The admin preview painted its header bar with the primary colour** even when the set
  defines a separate header (Cunningham's white over a blue primary). The mini app shell now reads
  `--nldesign-color-header-background` / `-text`, falling back to the primary only when the
  page carries no header token.
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
