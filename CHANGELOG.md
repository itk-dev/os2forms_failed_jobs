# Changelog for os2forms_failed_jobs

Nedenfor ses dato for release og beskrivelse af opgaver som er implementeret.

## [Under udvikling]

* Optimerede database forespørgsler i personalized view'et.

## [1.7.0] 2025-05-02

* Updaterer minimal php version
* Tilføj peronaliseret view til error log
* Tilføj nye fields til advanced queue views
* Tilføj nye filtre til advanced queue views
* Tilføj side til "Mine form fejl"
* Tilføj manuel håndtering af fejlede jobs.
* Ændre retry håndtering

## [1.6.0] 2025-03-11

* Tillod jobs uden relation til indsendelser at blive genkørt.

## [1.5.1] 2024-10-02

* Tilføejde primary key til relationstabellen.

## [1.5.0] 2024-07-05

* Drupal 10 kompatibilitet

## [1.4.0] 2024-01-05

* Fejl ved manglende kø elementer https://leantime.itkdev.dk/dashboard/home#/tickets/showTicket/342
* Error loggen vise nogle gange AAA som state. [SUPP0RT-1381](https://jira.itkdev.dk/browse/SUPP0RT-1381)
* Oprydning i relation køen efter slettede submisisons
* Tilføjelse af CHANGELOG.md

[Under udvikling]: https://github.com/itk-dev/os2forms_failed_jobs/compare/1.7.0...HEAD
[1.7.0]: https://github.com/itk-dev/os2forms_failed_jobs/compare/1.6.0...1.7.0
[1.6.0]: https://github.com/itk-dev/os2forms_failed_jobs/compare/1.5.1...1.6.0
[1.5.1]: https://github.com/itk-dev/os2forms_failed_jobs/compare/1.5.0...1.5.1
[1.5.0]: https://github.com/itk-dev/os2forms_failed_jobs/compare/1.4.0...1.5.0
[1.4.0]: https://github.com/itk-dev/os2forms_failed_jobs/compare/1.3.2...1.4.0

