<?php

namespace App\Enums;

enum UserRole: string
{
    /**
     * Equipe da Castilho Soluções Digitais: gerencia servidores, usuários e
     * auditoria de todas as prefeituras. Não pertence a um tenant específico
     * (tenant_id nulo) — enxerga todos os municípios.
     */
    case AdministradorInterno = 'administrador_interno';

    /**
     * Servidor da prefeitura: lança convênios, modifica marcos temporais e
     * anexa contratos. Restrito ao próprio tenant.
     */
    case GestorConvenios = 'gestor_convenios';

    /**
     * Órgão de controle interno: acesso somente leitura e exportação de
     * relatórios para auditorias do TCE. Restrito ao próprio tenant.
     */
    case FiscalControleInterno = 'fiscal_controle_interno';

    public function label(): string
    {
        return match ($this) {
            self::AdministradorInterno => 'Administrador Interno',
            self::GestorConvenios => 'Gestor de Convênios',
            self::FiscalControleInterno => 'Fiscal de Controle Interno',
        };
    }

    /**
     * Administrador Interno não pertence a nenhuma prefeitura: enxerga
     * todos os tenants (o TenantScope não filtra quando tenant_id é nulo).
     */
    public function exigeTenant(): bool
    {
        return $this !== self::AdministradorInterno;
    }
}
