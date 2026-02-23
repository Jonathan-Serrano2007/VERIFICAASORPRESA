PRAGMA foreign_keys = ON;

DROP TABLE IF EXISTS Catalogo;
DROP TABLE IF EXISTS Fornitori;
DROP TABLE IF EXISTS Pezzi;

CREATE TABLE Fornitori (
    fid TEXT PRIMARY KEY,
    fnome TEXT NOT NULL,
    indirizzo TEXT NOT NULL
);

CREATE TABLE Pezzi (
    pid TEXT PRIMARY KEY,
    pnome TEXT NOT NULL,
    colore TEXT NOT NULL
);

CREATE TABLE Catalogo (
    fid TEXT NOT NULL,
    pid TEXT NOT NULL,
    costo REAL NOT NULL CHECK (costo >= 0),
    PRIMARY KEY (fid, pid),
    FOREIGN KEY (fid) REFERENCES Fornitori(fid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (pid) REFERENCES Pezzi(pid) ON DELETE CASCADE ON UPDATE CASCADE
);

INSERT INTO Fornitori (fid, fnome, indirizzo) VALUES
('F1', 'Acme', 'Via Roma 10'),
('F2', 'Beta', 'Via Milano 20'),
('F3', 'Gamma', 'Corso Torino 30'),
('F4', 'Delta', 'Piazza Napoli 5');

INSERT INTO Pezzi (pid, pnome, colore) VALUES
('P1', 'Bullone', 'rosso'),
('P2', 'Vite', 'verde'),
('P3', 'Dado', 'rosso'),
('P4', 'Rondella', 'blu'),
('P5', 'Molla', 'verde'),
('P6', 'Distanziale', 'nero');

INSERT INTO Catalogo (fid, pid, costo) VALUES
('F1', 'P1', 10.0),
('F1', 'P2', 15.0),
('F1', 'P3', 20.0),
('F1', 'P4', 18.0),
('F1', 'P5', 19.0),
('F1', 'P6', 25.0),
('F2', 'P1', 12.0),
('F2', 'P2', 14.0),
('F2', 'P4', 16.0),
('F3', 'P1', 11.0),
('F3', 'P3', 22.0),
('F4', 'P1', 13.0),
('F4', 'P2', 17.0),
('F4', 'P3', 23.0),
('F4', 'P4', 19.0),
('F4', 'P5', 21.0);
