import { prisma } from '../src/lib/prisma';

if (process.env.NODE_ENV === 'production') {
    console.log('Cannot run seed in production!');
    process.exit(0);
}

async function main() {
    //Start of seed permissions data
    const permissions = [
        { name: 'View', permission_code: 'view' },
        { name: 'Create', permission_code: 'create' },
        { name: 'Edit', permission_code: 'edit' },
        { name: 'Delete', permission_code: 'delete' },
        { name: 'Admin', permission_code: 'admin' },
    ];

    for (const perm of permissions) {
        const result = await prisma.ms_permissions.upsert({
            where: { permission_code: perm.permission_code },
            update: {}, // No update needed if exists (or update fields if you want)
            create: perm,
        });
        console.log(`Permission "${perm.name}" (${perm.permission_code}) → ${result.id ? 'Created' : 'Already exists'}`);
    }

    //End of seed permissions data

    console.log("Seed done");
}

main().
    catch(e => console.error(e)).
    finally(() => {
        console.log("Disconnected with DB")
        prisma.$disconnect()
    });