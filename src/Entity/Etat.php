<?php

namespace App\Entity;

enum Etat: string
{
    case CR = 'Créée';
    case OU = 'Ouverte';
    case CL = 'Cloturé';
    case EC = 'Activité En Cours';
    case PA = 'Passée';
    case AN = 'Annulée';

}
