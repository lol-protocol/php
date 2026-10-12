-- Marca explícita de si una persona vive, para corregir lo que se deduce de sus
-- fechas (ver App\Support\Privacidad):
--   NULL  = deducirlo: vive si no tiene defunción y nació hace menos de
--           Privacidad::ANIOS_VIVA años.
--   TRUE  = vive, aunque sus fechas digan otra cosa o falten.
--   FALSE = ya falleció, aunque falte su defunción o su nacimiento sea reciente.
-- A quien no es el propietario se le oculta lo que se sabe de las personas que viven.
ALTER TABLE personas ADD COLUMN viva BOOLEAN;
