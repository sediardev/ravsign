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
    /** Field size in PDF points. Editable per field, independent of `type`. */
    width: number;
    height: number;
    /** Image data URL or plain text once signed. */
    value: string | null;
};

export type SignLink = {
    signerId: string;
    /** Absolute URL, '/sign/{token}'. */
    url: string;
    token: string;
    status: 'pendiente' | 'firmado';
};

export type DocumentItem = {
    id: string;
    name: string;
    status: DocumentStatus;
    date: string;
    pages: number;
    signers: Signer[];
    fields: SignField[];
    /** Empty while the document is a draft. */
    links: SignLink[];
};

/** A signer as another signer sees them: no email, no link. */
export type PublicSigner = Pick<Signer, 'id' | 'name' | 'siglas' | 'color'> & {
    signed: boolean;
};

/** The signer using their link on the signing screen. */
export type SigningSigner = PublicSigner & {
    email: string;
    /** The credential in the signer's own link. */
    token: string;
    /** Their own absolute link, '/sign/{token}'. */
    link: string;
};

/** The document as the signing screen receives it. */
export type SigningDocument = {
    id: string;
    name: string;
    status: DocumentStatus;
    pages: number;
    signers: PublicSigner[];
    fields: SignField[];
};
