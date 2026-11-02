package com.bens.core.merchant.entity;

import com.bens.core.common.entity.BaseEntity;
import jakarta.persistence.CascadeType;
import jakarta.persistence.Column;
import jakarta.persistence.Entity;
import jakarta.persistence.OneToMany;
import jakarta.persistence.Table;
import java.util.ArrayList;
import java.util.List;

@Entity
@Table(name = "merchants")
public class Merchant extends BaseEntity {

    @Column(nullable = false)
    private String name;

    @Column(name = "api_key_hash", nullable = false, unique = true)
    private String apiKeyHash;

    @Column(name = "webhook_secret_hash", nullable = false)
    private String webhookSecretHash;

    @OneToMany(mappedBy = "merchant", cascade = CascadeType.ALL, orphanRemoval = true)
    private List<Collaborator> collaborators = new ArrayList<>();

    public String getName() {
        return name;
    }

    public void setName(String name) {
        this.name = name;
    }

    public String getApiKeyHash() {
        return apiKeyHash;
    }

    public void setApiKeyHash(String apiKeyHash) {
        this.apiKeyHash = apiKeyHash;
    }

    public String getWebhookSecretHash() {
        return webhookSecretHash;
    }

    public void setWebhookSecretHash(String webhookSecretHash) {
        this.webhookSecretHash = webhookSecretHash;
    }

    public List<Collaborator> getCollaborators() {
        return collaborators;
    }

    public void setCollaborators(List<Collaborator> collaborators) {
        this.collaborators = collaborators;
    }

    public void addCollaborator(Collaborator collaborator) {
        collaborators.add(collaborator);
        collaborator.setMerchant(this);
    }

    public void removeCollaborator(Collaborator collaborator) {
        collaborators.remove(collaborator);
        collaborator.setMerchant(null);
    }
}
