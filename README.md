# LP — Balance Consultoria | Governança Financeira

Protótipo de layout da landing page de Governança Financeira da **Balance Consultoria Empresarial** (Goiânia).

- Frontend: `index.html`, `assets/css/styles.css` e scripts em `assets/js/`
- Formulário: `send-lead.php`, com envio por `mail()` para o endereço confirmado do cliente
- Imagens: `images/` (logos e fotos extraídos do Figma do cliente — Conteúdo | OUT 2026, página Balance)
- Fonte: Figtree (+ Plus Jakarta Sans nos rótulos), via Google Fonts
- Paleta: marrom `#732b00` · marrom card `#51250b` · fundo escuro `#421900` · pêssego `#f5ae83` / `#f0bb9a`
- Copy: Google Doc "LP | Balance Consultoria | Governança Financeira"
- Publicação: cPanel Git Version Control, com receita em `.cpanel.yml`
- Destino de deploy: `/home1/balanc29/gestao.balanceconsultoria.com.br/`
- Tracking: GTM `GTM-TSCK2XHW` com Consent Mode V2; Clarity `z0pn4t9qvc` condicionado a Analytics

## Pendências de produção

- Credenciais, números/tempo de mercado, logos de clientes e case/depoimento (Seção 05) — retirados da página até virem validados
- Conteúdo jurídico aprovado, e-mail de privacidade e canal de direitos do titular
- Política de Privacidade e Termos de Uso públicos; as URLs usuais do site institucional retornavam 404 na auditoria de 20/10/2026
- Confirmação se `gestao.balanceconsultoria.com.br` deixará de ser homologação para remover `noindex` e gerar sitemap
- Validação em produção do `mail()` do servidor e da entrega no endereço de destino
- Revisão das tags internas do GTM no modo Preview e ativação do Consent Mode no projeto do Clarity
- Validação da CSP em Report-Only antes de convertê-la em política efetiva

Desenvolvido por **3ADS**.
