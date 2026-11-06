<?php

declare(strict_types=1);

return [
    'media_conflict' => 'Pro Beitrag ist nur ein Medientyp möglich (:conflict).',
    'text_required' => 'Für Text-Beiträge ist ein Text erforderlich.',
    'text_too_long' => 'Der Text darf höchstens :max Zeichen lang sein (aktuell :length).',
    'url_required' => 'Die URL (:field) ist erforderlich.',
    'url_https' => 'Die URL (:field) muss eine gültige, öffentlich erreichbare https-URL sein.',
    'url_invalid' => 'Die URL (:field) ist ungültig.',
    'alt_text_requires_media' => 'Ein Alternativtext ist nur bei Bild- und Video-Beiträgen möglich.',
    'alt_text_too_long' => 'Der Alternativtext darf höchstens :max Zeichen lang sein.',
    'carousel_item_count' => 'Ein Karussell benötigt zwischen :min und :max Elemente (aktuell :count).',
    'carousel_item_type' => 'Karussell-Elemente müssen Bilder oder Videos sein.',
    'poll_requires_text' => 'Umfragen sind nur bei Text-Beiträgen möglich.',
    'poll_option_count' => 'Eine Umfrage benötigt zwischen :min und :max Optionen (aktuell :count).',
    'poll_option_length' => 'Jede Umfrageoption muss zwischen :min und :max Zeichen lang sein.',
    'link_attachment_requires_text' => 'Ein Link-Anhang ist nur bei Text-Beiträgen möglich.',
    'link_limit' => 'Ein Beitrag darf höchstens :max Links enthalten (aktuell :count).',
    'auto_publish_requires_text' => 'Automatisches Veröffentlichen ist nur bei Text-Beiträgen möglich.',
    'topic_tag_invalid' => 'Das Themen-Tag muss 1 bis :max Zeichen lang sein und darf weder "." noch "&" enthalten.',
    'country_code_invalid' => 'Der Ländercode ":code" ist kein gültiger ISO-3166-1-Alpha-2-Code.',
    'spoiler_limit' => 'Ein Beitrag darf höchstens :max Spoiler enthalten.',
    'spoiler_range' => 'Der Spoiler-Bereich liegt außerhalb des Textes.',
    'text_attachment_requires_text' => 'Ein Text-Anhang ist nur bei Text-Beiträgen möglich.',
    'text_attachment_too_long' => 'Der Text-Anhang darf höchstens :max Zeichen lang sein.',
];
