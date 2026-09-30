export type DocumentStatus = 'borrador' | 'pendiente' | 'completado';
export type FieldType = 'firma' | 'iniciales' | 'fecha' | 'nombre';

export type Signer = {
    id: string;
    name: string;
    siglas: string;
    email: string;
    color: string;
};

export type SignField = {
    id: string;
    type: FieldType;
    signerId: string;
    /** Zero-based page index. */
    page: number;
    /** Percentage of the page width (0-100). */
    x: number;
    /** Percentage of the page height (0-100). */
    y: number;
    /** Image data URL or plain text once signed. */
    value: string | null;
};

export type DocumentItem = {
    id: number;
    name: string;
    status: DocumentStatus;
    date: string;
    signers: Signer[];
    fields: SignField[];
};
