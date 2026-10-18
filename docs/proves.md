# Correspondencia de las pruebas obligatorias (apartado 21 del enunciado)

| # | Caso | Prueba |
|---|---|---|
| 1 | Dos F7 a la vez | `tests/fixtures/scheduling/01-dos-f7-simultanis.json` (PHP: `SharedFixturesTest`, TS: `schedulingFixtures.test.ts`) |
| 2 | Un tercer F7 da conflicto | Fixture `02-tercer-f7`; `ScheduleApiTest::test_move_validation_reports_conflicts_without_saving` |
| 3 | Un F11 bloquea Camp 1 y Camp 2 | Fixture `04-f11-bloqueja-camp` |
| 4 | No se puede colocar un F7 durante un F11 | Fixture `04-f11-bloqueja-camp`; `ScheduleApiTest::test_cannot_confirm_with_critical_conflicts` |
| 5 | No se puede colocar un F11 durante un F7 | Fixture `05-f11-durant-f7` |
| 6 | Duraciones y márgenes | Fixtures `07-durades-i-marges` y `08-interval-minim` |
| 7 | No se puede salir de las franjas | Fixture `09-fora-de-franja`; `MatchApiTest::test_home_kickoff_must_be_*`; `ScheduleApiTest::test_backend_rejects_impossible_moves` |
| 8 | Reglas del Primer equip | `ProposalEngineTest::test_primer_equip_is_proposed_on_saturday_at_17`; `ScheduleApiTest::test_proposal_applies_the_club_rules` |
| 9 | Benjamí A y B en campos diferentes | `ProposalEngineTest::test_benjami_a_and_b_share_sunday_12_30_on_different_fields` |
| 10 | Benjamí C el sábado a las 12:30 | `ProposalEngineTest::test_benjami_c_is_proposed_on_saturday_at_12_30` |
| 11 | Aleví el sábado a las 11:15 | `ProposalEngineTest::test_alevi_teams_are_proposed_on_saturday_at_11_15` |
| 12 | Un bloqueo impide programar | Fixture `10-bloqueig`; `ProposalEngineTest::test_field_blocks_are_avoided`; `ScheduleApiTest::test_field_blocks_and_away_matches_are_considered` |
| 13 | Los partidos fuera no ocupan el campo | Fixture `11-fora-no-ocupa`; `ProposalEngineTest::test_away_matches_do_not_take_the_home_field` |
| 14 | No se puede confirmar con conflictos críticos | `ScheduleApiTest::test_cannot_confirm_with_critical_conflicts`, `…_with_unscheduled_home_matches`; frontend `schedule.test.tsx` |
| 15 | Guardar un borrador no envía mensajes | `ScheduleApiTest::test_saving_a_draft_never_sends_messages`; `WhatsAppNotificationTest::test_preview_prepares_…_without_sending` |
| 16 | No se duplica un envío de WhatsApp | `WhatsAppNotificationTest::test_message_is_sent_once_and_never_duplicated` |
| 17 | Un usuario sin permisos no puede cambiar la configuración | `ConfigurationAuthorizationTest`, `RouteProtectionTest::test_configuration_routes_are_forbidden_to_managers`, `CategoryApiTest::test_manager_cannot_change_categories`, `WhatsAppNotificationTest::test_manager_cannot_change_templates_or_destinations` |
| 18 | La contraseña no se guarda en texto plano | `PasswordStorageTest` |
| 19 | Se rechazan los tipos de archivo no permitidos | `ClubLogoTest` (escudo del club: SVG, GIF, PHP disfrazado de PNG, extensión `.php` y tamaño rechazados) |
| 20 | La regeneración respeta los partidos bloqueados | `ProposalEngineTest::test_regeneration_respects_locked_matches`; `ScheduleApiTest::test_manual_move_locks_the_match_and_regeneration_respects_it` |
| 21 | Imágenes de sábado y domingo | `ImageGenerationTest::test_saturday_and_sunday_images_are_generated_at_1080x1920`, `…test_chromium_renders_a_real_1080x1920_image`, `LayoutPlannerTest` |
| 22 | Textos visibles en catalán | `CatalanTranslationsTest`; frontend `i18n.test.ts` (sin inglés ni castellano habituales) y `noHardcodedText.test.ts` (sin textos JSX escritos directamente) |

Otras pruebas relevantes: determinismo e idempotencia de la propuesta (`ProposalEngineTest`), concurrencia `409` (`ScheduleApiTest`, `MatchApiTest`), CSRF (`CsrfProtectionTest`), cabeceras y CSP (`SecurityHeadersTest`), fijación de sesión, HSTS, registros sin secretos, límite de peticiones y auditoría (`HardeningTest`), y que todas las rutas exigen sesión (`RouteProtectionTest`).
