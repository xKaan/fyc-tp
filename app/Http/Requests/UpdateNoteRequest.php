<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * Les regles de modification sont aujourd'hui exactement celles de la
 * creation. La classe existe quand meme : c'est elle que l'on modifiera le
 * jour ou les deux divergeront, sans toucher au formulaire de creation.
 */
class UpdateNoteRequest extends StoreNoteRequest {}
