import { prisma } from '../src/lib/prisma';

if (process.env.NODE_ENV === 'production') {
    console.log('Cannot run seed in production!');
    process.exit(0);
}

async function main() {
    await prisma.role.upsert({
        where: { id: 2 },
        update: { name: 'Super Admin 2' },
        create: { name: 'Super Admin 2', title: 'Super Admin 2', description: 'Super Admin 2' }
    });

    console.log("Start of Seeding Data - ");
    //Start of users data
    const users = [
        { name: 'Admin', email: 'admin@example.com', password: 'admin', mobile: '1234567890' },
        { name: 'User', email: 'user@example.com', password: 'user', mobile: '1234567890' }
    ];

    for (const user of users) {
        const result = await prisma.user.upsert({
            where: { email: user.email },
            update: {}, // No update needed if exists (or update fields if you want)
            create: user,
        });
        console.log(`User "${user.name}" (${user.email}) → ${result.id ? 'Created' : 'Already exists'}`);
    }

    //End of users data

    //Start of permissions data
    const permissions = [
        { name: 'View', permission_code: 'view' },
        { name: 'Create', permission_code: 'create' },
        { name: 'Edit', permission_code: 'edit' },
        { name: 'Delete', permission_code: 'delete' },
        { name: 'Admin', permission_code: 'admin' },
    ];

    for (const perm of permissions) {
        const result = await prisma.permission.upsert({
            where: { permission_code: perm.permission_code },
            update: {}, // No update needed if exists (or update fields if you want)
            create: perm,
        });
        console.log(`Permission "${perm.name}" (${perm.permission_code}) → ${result.id ? 'Created' : 'Already exists'}`);
    }

    //End of permissions data

    console.log("Seed done");
}

main().
    catch(e => console.error(e)).
    finally(() => {
        console.log("Disconnected with DB")
        prisma.$disconnect()
    });