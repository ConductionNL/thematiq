# Tasks: openproject-in-the-app-menu

- [x] 1 `OpenProjectModules`: the address, the modules, the section.
- [x] 2 `AppMenuService::inject()` adds the section to the menu's state.
- [x] 3 `js/app-menu.js` draws it; `css/header-workplace.css` styles the links.
- [x] 4 `OpenProjectLinkController` and the admin field; strings in English and Dutch.
- [x] 5 Tests: `AppMenuServiceTest`, `OpenProjectLinkControllerTest`, `tests/vitest/appMenu.spec.js`.
- [x] 6 Check the module paths against a running OpenProject.
      Live pass 17 Nov 2026 against OpenProject 17.9.1 (image openproject-thematiq:pl): all nine
      paths of `OpenProjectModules::MODULES` route (`rails runner` recognize_path: projects#index,
      my/page#show, work_packages#index, gantt/gantt#index, boards/boards#index,
      team_planner/team_planner#overview, calendar/calendars#index, meetings#index, news#index) and
      answer 302 to the login without a session, where an unknown path answers 404. Eight keys are
      items of OpenProject's top menu; `my_page` is not (My page sits in the account menu), which
      changes nothing for the link.
