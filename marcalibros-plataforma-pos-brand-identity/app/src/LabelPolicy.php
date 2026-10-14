<?php

namespace MarcalibrosApp;

use DefamatoryContentReview\Chat\ChatLineReviewer;

/**
 * Decide si el texto de un marcalibro se puede publicar. El marcalibro vive en
 * nuestro dominio y se comparte por enlace: un «Fulano es un estafador» sería
 * una difamación publicada por la plataforma (ver PRIVACIDAD-Y-HONRA.md en la
 * raíz del repositorio). Lo revisa defamatory-content-review en español e
 * inglés. Como aquí no hay nadie que revise a mano, lo que la librería manda a
 * revisión (acusaciones, datos personales, insultos leves) tampoco se acepta.
 */
final class LabelPolicy
{
    private const LANGUAGES = ['spa', 'eng'];

    /** @var list<ChatLineReviewer> */
    private array $reviewers;

    public function __construct(?string $configDirectory = null)
    {
        $configDirectory ??= self::libraryDirectory() . '/config';
        $this->reviewers = array_map(fn (string $language): ChatLineReviewer => ChatLineReviewer::create($configDirectory, $language), self::LANGUAGES);
    }

    /** Null si el texto se puede usar; si no, el mensaje para la persona. */
    public function problem(?string $label): ?string
    {
        if ($label === null || trim($label) === '') {
            return null;
        }
        $decisions = array_map(fn (ChatLineReviewer $reviewer): string => $reviewer->review($label)->getDecision(), $this->reviewers);
        if (in_array('reject', $decisions, true)) {
            return 'El texto del marcalibro tiene palabras ofensivas. Escribe otro.';
        }
        if (in_array('review', $decisions, true)) {
            return 'El texto del marcalibro no se puede usar: no publicamos insultos, acusaciones ni datos personales de nadie. Escribe otro.';
        }

        return null;
    }

    /** Con Composer, donde lo instaló; sin Composer, la carpeta vecina del monorepo. */
    private static function libraryDirectory(): string
    {
        $package = 'lol-protocol/defamatory-content-review';
        if (class_exists(\Composer\InstalledVersions::class) && \Composer\InstalledVersions::isInstalled($package)) {
            return (string) \Composer\InstalledVersions::getInstallPath($package);
        }

        return dirname(__DIR__, 3) . '/defamatory-content-review';
    }
}
