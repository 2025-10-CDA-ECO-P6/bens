package com.bens.core.merchant.mapper;

import com.bens.core.merchant.dto.CollaboratorResponse;
import com.bens.core.merchant.dto.MerchantResponse;
import com.bens.core.merchant.dto.RegisterRequest;
import com.bens.core.merchant.entity.Collaborator;
import com.bens.core.merchant.entity.Merchant;
import org.mapstruct.Mapper;
import org.mapstruct.Mapping;

@Mapper(componentModel = "spring")
public interface EntitiesMapper {

    @Mapping(target = "id", ignore = true)
    @Mapping(target = "createdAt", ignore = true)
    @Mapping(target = "updatedAt", ignore = true)
    @Mapping(target = "apiKeyHash", ignore = true)
    @Mapping(target = "webhookSecretHash", ignore = true)
    @Mapping(target = "collaborators", ignore = true)
    Merchant toMerchant(RegisterRequest request);

    @Mapping(target = "id", ignore = true)
    @Mapping(target = "createdAt", ignore = true)
    @Mapping(target = "updatedAt", ignore = true)
    @Mapping(target = "merchant", ignore = true)
    @Mapping(target = "passwordHash", ignore = true)
    @Mapping(target = "active", constant = "true")
    Collaborator toCollaborator(RegisterRequest request);

    MerchantResponse toMerchantResponse(Merchant merchant);

    @Mapping(target = "merchantId", source = "merchant.id")
    CollaboratorResponse toResponse(Collaborator collaborator);
}
