import React from 'react';
import googleClassroom from '@/lib/config/googleClassroom';
import {
  Anchor,
  Breadcrumbs,
  Button,
  Container,
  Flex,
  Grid,
  Group,
  Paper,
  Title,
  Text,
  ActionIcon,
  Accordion,
  GridCol,
} from '@mantine/core';
import { IconExternalLink, IconPlus } from '@tabler/icons-react';
import Link from 'next/link';
import CourseWork from './CourseWork';

type Props = {
  params: {
    courseId: string;
  };
};

export async function getCourse(id: string) {
  const classroom = await googleClassroom();
  const { data: course } = await classroom.courses.get({ id });
  return course;
}

export async function generateMetadata({ params: { courseId } }: Props) {
  const course = await getCourse(courseId);
  return {
    title: course.name,
  };
}

export async function getCourseWorkList(courseId: string) {
  const classroom = await googleClassroom();
  const { data: courseWork } = await classroom.courses.courseWork.list({
    courseId,
  });
  return courseWork.courseWork;
}

export default async function CoursePage({ params: { courseId } }: Props) {
  const [course, courseWorkList] = await Promise.all([
    getCourse(courseId),
    getCourseWorkList(courseId),
  ]);

  const items = [{ title: 'Courses', href: '.' }].map((item, index) => (
    <Anchor component={Link} href={item.href} key={index}>
      {item.title}
    </Anchor>
  ));

  return (
    <Container mt='lg' size='xl'>
      <Breadcrumbs>{items}</Breadcrumbs>
      <Group mt={'lg'} justify='space-between'>
        <Title>{course.name}</Title>
        <Button
          component={Link}
          href={`/courses/${courseId}/work/new`}
          variant='light'
          leftSection={<IconPlus size='1rem' />}
        >
          Create
        </Button>
      </Group>
      <Grid mt='xl'>
        <GridCol span={{ base: 12, md: 4 }}>
          <Paper withBorder px='xl' py='sm'>
            <Flex justify='space-between'>
              <Text>{course.name}</Text>
              <ActionIcon
                variant='subtle'
                color='gray'
                component={Link}
                href={course.alternateLink || ''}
                target='_blank'
              >
                <IconExternalLink size={'1rem'} />
              </ActionIcon>
            </Flex>
          </Paper>
        </GridCol>
        <GridCol span={{ base: 12, md: 8 }}>
          <Accordion variant='separated' radius='xs'>
            {courseWorkList?.map((work) => (
              <CourseWork key={work.id} courseWork={work} />
            ))}
          </Accordion>
        </GridCol>
      </Grid>
    </Container>
  );
}
